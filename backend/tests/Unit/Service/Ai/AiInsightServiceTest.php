<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ai;

use App\Entity\AiChatMessage;
use App\Entity\AiInsight;
use App\Entity\Audit;
use App\Entity\AuditFinding;
use App\Entity\Enum\AiInsightType;
use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use App\Entity\Enum\RepositoryProvider;
use App\Entity\Repository;
use App\Entity\RepositoryScan;
use App\Entity\User;
use App\Repository\AiChatMessageRepository;
use App\Repository\AiInsightRepository;
use App\Service\Ai\AiInsightService;
use App\Service\Ai\AiPayloadBuilder;
use App\Service\AiEngineClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Not every test needs to assert on every collaborator (e.g. the chat
 * message repository is irrelevant to the finding-explanation tests) —
 * the mocks are still valid test doubles, just unused in some cases.
 */
#[AllowMockObjectsWithoutExpectations]
final class AiInsightServiceTest extends TestCase
{
    private AiInsightRepository&MockObject $insightRepository;
    private AiChatMessageRepository&MockObject $chatMessageRepository;
    private EntityManagerInterface&MockObject $entityManager;
    /** @var list<MockResponse> */
    private array $queuedResponses;
    /** @var list<array{method: string, url: string, options: array}> */
    private array $recordedRequests = [];

    protected function setUp(): void
    {
        $this->insightRepository = $this->createMock(AiInsightRepository::class);
        $this->chatMessageRepository = $this->createMock(AiChatMessageRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->queuedResponses = [];
        $this->recordedRequests = [];
    }

    /** AiEngineClient is `final`, so it is exercised for real here against a MockHttpClient
     * rather than mocked — this also verifies the actual HTTP/JSON wiring, not just call counts. */
    private function makeService(): AiInsightService
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->recordedRequests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($this->queuedResponses) ?? new MockResponse('{}', ['http_code' => 200]);
        });

        return new AiInsightService(
            new AiEngineClient($httpClient, 'http://ai-engine.test', null),
            new AiPayloadBuilder(),
            $this->insightRepository,
            $this->chatMessageRepository,
            $this->entityManager,
        );
    }

    private function queueJsonResponse(array $data): void
    {
        $this->queuedResponses[] = new MockResponse(json_encode($data, JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    private function makeAudit(): Audit
    {
        $owner = new User('owner@example.com', 'hash');
        $repository = new Repository($owner, 'acme/widgets', 'https://github.com/acme/widgets', RepositoryProvider::GitHub);
        $scan = new RepositoryScan($repository);
        $audit = new Audit($scan);
        $scan->attachAudit($audit);

        return $audit;
    }

    public function testExplainFindingReturnsCachedContentWithoutCallingClient(): void
    {
        $audit = $this->makeAudit();
        $finding = new AuditFinding($audit, 'r1', FindingCategory::Security, FindingSeverity::High, 'T', 'D', 'php', 4);
        $cached = new AiInsight($audit, AiInsightType::FindingExplanation, ['ai_recommendation' => 'cached'], 'mock', $finding);

        $this->insightRepository->expects(self::once())->method('findCachedFindingExplanation')->with($finding)->willReturn($cached);

        $result = $this->makeService()->explainFinding($finding);

        self::assertSame(['ai_recommendation' => 'cached'], $result);
        self::assertCount(0, $this->recordedRequests);
    }

    public function testExplainFindingCallsClientAndPersistsWhenNotCached(): void
    {
        $audit = $this->makeAudit();
        $finding = new AuditFinding($audit, 'r1', FindingCategory::Security, FindingSeverity::High, 'T', 'D', 'php', 4);

        $this->insightRepository->method('findCachedFindingExplanation')->willReturn(null);
        $this->queueJsonResponse(['ai_recommendation' => 'fresh', 'provider' => 'mock']);

        $this->entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(AiInsight::class));
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->makeService()->explainFinding($finding);

        self::assertSame('fresh', $result['ai_recommendation']);
        self::assertCount(1, $this->recordedRequests);
        self::assertStringEndsWith('/ai/findings/explain', $this->recordedRequests[0]['url']);
    }

    public function testExplainFindingWithRefreshBypassesCacheAndRemovesOldRow(): void
    {
        $audit = $this->makeAudit();
        $finding = new AuditFinding($audit, 'r1', FindingCategory::Security, FindingSeverity::High, 'T', 'D', 'php', 4);
        $existing = new AiInsight($audit, AiInsightType::FindingExplanation, ['ai_recommendation' => 'stale'], 'mock', $finding);

        $this->insightRepository->method('findCachedFindingExplanation')->willReturn($existing);
        $this->queueJsonResponse(['ai_recommendation' => 'regenerated', 'provider' => 'mock']);

        $this->entityManager->expects(self::once())->method('remove')->with($existing);
        $this->entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(AiInsight::class));

        $result = $this->makeService()->explainFinding($finding, refresh: true);

        self::assertSame('regenerated', $result['ai_recommendation']);
    }

    public function testGetExecutiveSummaryReturnsCachedContentWithoutCallingClient(): void
    {
        $audit = $this->makeAudit();
        $cached = new AiInsight($audit, AiInsightType::ExecutiveSummary, ['ai_recommendation' => ['cached step']], 'mock');

        $this->insightRepository->expects(self::once())->method('findCachedAuditInsight')->with($audit, AiInsightType::ExecutiveSummary)->willReturn($cached);

        $result = $this->makeService()->getExecutiveSummary($audit);

        self::assertSame(['ai_recommendation' => ['cached step']], $result);
        self::assertCount(0, $this->recordedRequests);
    }

    public function testChatPersistsUserAndAssistantMessagesAndExcludesTheNewQuestionFromHistorySentToClient(): void
    {
        $audit = $this->makeAudit();
        $priorMessage = new AiChatMessage($audit, 'user', 'earlier question');
        $this->chatMessageRepository->method('findConversation')->willReturn([$priorMessage]);
        $this->queueJsonResponse(['ai_interpretation' => ['answer' => 'the answer'], 'provider' => 'mock']);

        $this->entityManager->expects(self::exactly(2))->method('persist');
        $this->entityManager->expects(self::exactly(2))->method('flush');

        $result = $this->makeService()->chat($audit, 'new question');

        self::assertSame('the answer', $result['ai_interpretation']['answer']);

        $sentPayload = json_decode($this->recordedRequests[0]['options']['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('new question', $sentPayload['question']);
        self::assertCount(1, $sentPayload['conversation_history']);
        self::assertSame('earlier question', $sentPayload['conversation_history'][0]['content']);
    }
}
