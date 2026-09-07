<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\AiChatMessage;
use App\Entity\AiInsight;
use App\Entity\Audit;
use App\Entity\AuditFinding;
use App\Entity\Enum\AiInsightType;
use App\Entity\Enum\FindingCategory;
use App\Repository\AiChatMessageRepository;
use App\Repository\AiInsightRepository;
use App\Service\AiEngineClient;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orchestrates the 5 AI features: builds the deterministic payload, calls
 * the ai-engine, and caches audit/finding-scoped results so a given insight
 * is only generated once unless a refresh is explicitly requested. Chat
 * responses are never cached — each question is answered fresh, but the
 * conversation itself is persisted for context and history.
 */
final class AiInsightService
{
    public function __construct(
        private readonly AiEngineClient $client,
        private readonly AiPayloadBuilder $payloadBuilder,
        private readonly AiInsightRepository $insightRepository,
        private readonly AiChatMessageRepository $chatMessageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<string, mixed> */
    public function explainFinding(AuditFinding $finding, bool $refresh = false): array
    {
        if (!$refresh) {
            $cached = $this->insightRepository->findCachedFindingExplanation($finding);
            if (null !== $cached) {
                return $cached->getContent();
            }
        }

        $result = $this->client->explainFinding([
            'finding' => $this->payloadBuilder->finding($finding),
            'repository' => $this->payloadBuilder->repository($finding->getAudit()),
        ]);

        $this->storeInsight($finding->getAudit(), AiInsightType::FindingExplanation, $result, $finding);

        return $result;
    }

    /** @return array<string, mixed> */
    public function getExecutiveSummary(Audit $audit, bool $refresh = false): array
    {
        if (!$refresh) {
            $cached = $this->insightRepository->findCachedAuditInsight($audit, AiInsightType::ExecutiveSummary);
            if (null !== $cached) {
                return $cached->getContent();
            }
        }

        $result = $this->client->generateExecutiveSummary([
            'audit' => $this->payloadBuilder->auditSummary($audit),
            'findings' => $this->payloadBuilder->topFindings($audit),
            'repository' => $this->payloadBuilder->repository($audit),
        ]);

        $this->storeInsight($audit, AiInsightType::ExecutiveSummary, $result);

        return $result;
    }

    /** @return array<string, mixed> */
    public function getArchitectureExplanation(Audit $audit, bool $refresh = false): array
    {
        if (!$refresh) {
            $cached = $this->insightRepository->findCachedAuditInsight($audit, AiInsightType::ArchitectureExplanation);
            if (null !== $cached) {
                return $cached->getContent();
            }
        }

        $result = $this->client->explainArchitecture([
            'repository' => $this->payloadBuilder->repository($audit),
            'findings' => $this->payloadBuilder->topFindings($audit, FindingCategory::Architecture),
        ]);

        $this->storeInsight($audit, AiInsightType::ArchitectureExplanation, $result);

        return $result;
    }

    /** @return array<string, mixed> */
    public function getRefactoringPlan(Audit $audit, bool $refresh = false): array
    {
        if (!$refresh) {
            $cached = $this->insightRepository->findCachedAuditInsight($audit, AiInsightType::RefactoringPlan);
            if (null !== $cached) {
                return $cached->getContent();
            }
        }

        $result = $this->client->generateRefactoringPlan([
            'audit' => $this->payloadBuilder->auditSummary($audit),
            'findings' => $this->payloadBuilder->topFindings($audit),
            'repository' => $this->payloadBuilder->repository($audit),
        ]);

        $this->storeInsight($audit, AiInsightType::RefactoringPlan, $result);

        return $result;
    }

    /** @return array<string, mixed> */
    public function chat(Audit $audit, string $question): array
    {
        $history = $this->chatMessageRepository->findConversation($audit);

        $userMessage = new AiChatMessage($audit, 'user', $question);
        $this->entityManager->persist($userMessage);
        $this->entityManager->flush();

        $result = $this->client->chat([
            'question' => $question,
            'audit' => $this->payloadBuilder->auditSummary($audit),
            'findings' => $this->payloadBuilder->topFindings($audit),
            'repository' => $this->payloadBuilder->repository($audit),
            'conversation_history' => array_map(
                static fn (AiChatMessage $message) => ['role' => $message->getRole(), 'content' => $message->getContent()],
                $history,
            ),
        ]);

        $answer = $result['ai_interpretation']['answer'] ?? '';
        $assistantMessage = new AiChatMessage($audit, 'assistant', $answer);
        $this->entityManager->persist($assistantMessage);
        $this->entityManager->flush();

        return $result;
    }

    /** @return AiChatMessage[] */
    public function getConversation(Audit $audit): array
    {
        return $this->chatMessageRepository->findConversation($audit);
    }

    /** @param array<string, mixed> $content */
    private function storeInsight(Audit $audit, AiInsightType $type, array $content, ?AuditFinding $finding = null): void
    {
        $existing = null !== $finding
            ? $this->insightRepository->findCachedFindingExplanation($finding)
            : $this->insightRepository->findCachedAuditInsight($audit, $type);

        if (null !== $existing) {
            $this->entityManager->remove($existing);
        }

        $insight = new AiInsight($audit, $type, $content, (string) ($content['provider'] ?? 'unknown'), $finding);
        $this->entityManager->persist($insight);
        $this->entityManager->flush();
    }
}
