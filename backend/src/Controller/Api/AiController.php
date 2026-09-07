<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Audit;
use App\Entity\AuditFinding;
use App\Entity\User;
use App\Repository\AuditFindingRepository;
use App\Repository\AuditRepository;
use App\Service\Ai\AiInsightService;
use App\Service\AiEngineException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/audits/{auditId}/ai')]
final class AiController
{
    public function __construct(
        private readonly AiInsightService $aiInsightService,
        private readonly AuditRepository $auditRepository,
        private readonly AuditFindingRepository $auditFindingRepository,
    ) {
    }

    #[Route('/summary', name: 'api_ai_summary', methods: ['POST'])]
    public function summary(string $auditId, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $audit = $this->findOwnedAudit($auditId, $user);
        if (null === $audit) {
            return new JsonResponse(['error' => 'Audit not found.'], 404);
        }

        return $this->withAiEngine(fn () => $this->aiInsightService->getExecutiveSummary($audit, $this->refresh($request)));
    }

    #[Route('/architecture', name: 'api_ai_architecture', methods: ['POST'])]
    public function architecture(string $auditId, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $audit = $this->findOwnedAudit($auditId, $user);
        if (null === $audit) {
            return new JsonResponse(['error' => 'Audit not found.'], 404);
        }

        return $this->withAiEngine(fn () => $this->aiInsightService->getArchitectureExplanation($audit, $this->refresh($request)));
    }

    #[Route('/refactoring-plan', name: 'api_ai_refactoring_plan', methods: ['POST'])]
    public function refactoringPlan(string $auditId, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $audit = $this->findOwnedAudit($auditId, $user);
        if (null === $audit) {
            return new JsonResponse(['error' => 'Audit not found.'], 404);
        }

        return $this->withAiEngine(fn () => $this->aiInsightService->getRefactoringPlan($audit, $this->refresh($request)));
    }

    #[Route('/findings/{findingId}/explain', name: 'api_ai_explain_finding', methods: ['POST'])]
    public function explainFinding(string $auditId, string $findingId, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $audit = $this->findOwnedAudit($auditId, $user);
        if (null === $audit) {
            return new JsonResponse(['error' => 'Audit not found.'], 404);
        }

        $finding = $this->findFindingInAudit($audit, $findingId);
        if (null === $finding) {
            return new JsonResponse(['error' => 'Finding not found.'], 404);
        }

        return $this->withAiEngine(fn () => $this->aiInsightService->explainFinding($finding, $this->refresh($request)));
    }

    #[Route('/chat', name: 'api_ai_chat_ask', methods: ['POST'])]
    public function chat(string $auditId, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $audit = $this->findOwnedAudit($auditId, $user);
        if (null === $audit) {
            return new JsonResponse(['error' => 'Audit not found.'], 404);
        }

        $question = $request->toArray()['question'] ?? null;
        if (!is_string($question) || '' === trim($question)) {
            return new JsonResponse(['error' => 'A non-empty "question" field is required.'], 422);
        }

        return $this->withAiEngine(fn () => $this->aiInsightService->chat($audit, $question));
    }

    #[Route('/chat', name: 'api_ai_chat_history', methods: ['GET'])]
    public function chatHistory(string $auditId, #[CurrentUser] User $user): JsonResponse
    {
        $audit = $this->findOwnedAudit($auditId, $user);
        if (null === $audit) {
            return new JsonResponse(['error' => 'Audit not found.'], 404);
        }

        $messages = array_map(
            static fn ($message) => [
                'role' => $message->getRole(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
            ],
            $this->aiInsightService->getConversation($audit),
        );

        return new JsonResponse($messages);
    }

    /** @param callable(): array<string, mixed> $callback */
    private function withAiEngine(callable $callback): JsonResponse
    {
        try {
            return new JsonResponse($callback());
        } catch (AiEngineException) {
            return new JsonResponse(['error' => 'The AI engine is currently unavailable. Please try again later.'], 502);
        }
    }

    private function refresh(Request $request): bool
    {
        return $request->query->getBoolean('refresh');
    }

    private function findOwnedAudit(string $id, User $user): ?Audit
    {
        $audit = $this->auditRepository->find($id);
        if (null === $audit) {
            return null;
        }

        $owner = $audit->getRepositoryScan()->getRepository()->getOwner();
        if ($owner->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            return null;
        }

        return $audit;
    }

    private function findFindingInAudit(Audit $audit, string $findingId): ?AuditFinding
    {
        $finding = $this->auditFindingRepository->find($findingId);
        if (null === $finding) {
            return null;
        }

        if ($finding->getAudit()->getId()->toRfc4122() !== $audit->getId()->toRfc4122()) {
            return null;
        }

        return $finding;
    }
}
