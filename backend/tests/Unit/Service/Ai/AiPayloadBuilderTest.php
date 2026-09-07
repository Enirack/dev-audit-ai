<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ai;

use App\Entity\Audit;
use App\Entity\AuditFinding;
use App\Entity\AuditScore;
use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use App\Entity\Enum\RepositoryProvider;
use App\Entity\Repository;
use App\Entity\RepositoryInventory;
use App\Entity\RepositoryScan;
use App\Entity\User;
use App\Service\Ai\AiPayloadBuilder;
use PHPUnit\Framework\TestCase;

final class AiPayloadBuilderTest extends TestCase
{
    private AiPayloadBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new AiPayloadBuilder();
    }

    private function makeAudit(bool $withInventory = true): Audit
    {
        $owner = new User('owner@example.com', 'hash');
        $repository = new Repository($owner, 'acme/widgets', 'https://github.com/acme/widgets', RepositoryProvider::GitHub);
        $scan = new RepositoryScan($repository);
        $audit = new Audit($scan);
        $scan->attachAudit($audit);

        if ($withInventory) {
            $inventory = new RepositoryInventory(
                $scan,
                totalFiles: 42,
                totalDirectories: 5,
                totalSizeBytes: 12345,
                binaryFileCount: 1,
                ignoredFileCount: 2,
                languageStats: ['python' => ['files' => 30, 'lines' => 5000], 'javascript' => ['files' => 12, 'lines' => 800]],
                extensionStats: ['.py' => 30, '.js' => 12],
                metadata: ['testDirectories' => ['tests']],
            );
            $scan->attachInventory($inventory);
        }

        return $audit;
    }

    private function addFinding(
        Audit $audit,
        string $ruleId,
        FindingCategory $category,
        FindingSeverity $severity,
        int $priority,
        float $confidence,
        ?string $filePath = null,
    ): AuditFinding {
        $finding = new AuditFinding($audit, $ruleId, $category, $severity, 'Title', 'Description', 'php', $priority);
        $finding->setConfidence($confidence);
        $finding->setFilePath($filePath);
        $audit->addFinding($finding);

        return $finding;
    }

    public function testRepositoryPayloadUsesInventoryAndPicksHighestLineCountLanguage(): void
    {
        $audit = $this->makeAudit();

        $payload = $this->builder->repository($audit);

        self::assertSame('acme/widgets', $payload['name']);
        self::assertSame('python', $payload['primary_language']);
        self::assertSame(42, $payload['total_files']);
        self::assertSame(12345, $payload['total_size_bytes']);
        self::assertSame(['testDirectories' => ['tests']], $payload['metadata_flags']);
    }

    public function testRepositoryPayloadHandlesMissingInventoryGracefully(): void
    {
        $audit = $this->makeAudit(withInventory: false);

        $payload = $this->builder->repository($audit);

        self::assertNull($payload['primary_language']);
        self::assertSame(0, $payload['total_files']);
        self::assertEquals(new \stdClass(), $payload['language_stats']);
    }

    public function testEmptyLanguageStatsEncodeAsJsonObjectNotArray(): void
    {
        $audit = $this->makeAudit();
        $scan = $audit->getRepositoryScan();
        $inventory = new RepositoryInventory($scan, 1, 0, 13, 0, 0, languageStats: [], extensionStats: [], metadata: []);
        // Simulate a real scan result where the inventory exists but detected no languages
        // (e.g. a repository with no recognized source files) — reproduces a real bug where
        // PHP's json_encode([]) serializes to `[]`, which the ai-engine's pydantic dict[str, Any]
        // schema rejects as "not a valid dictionary".
        $scan->attachInventory($inventory);

        $payload = $this->builder->repository($audit);

        self::assertJsonStringEqualsJsonString('{}', json_encode($payload['language_stats'], JSON_THROW_ON_ERROR));
    }

    public function testAuditSummaryMapsCategoryScores(): void
    {
        $audit = $this->makeAudit();
        $score = new AuditScore($audit, FindingCategory::Security, 71.2);
        $audit->addScore($score);

        $audit->setOverallScore(80.0);
        $payload = $this->builder->auditSummary($audit);

        self::assertSame(80.0, $payload['overall_score']);
        self::assertSame(['security' => 71.2], $payload['category_scores']);
    }

    public function testFindingPayloadMapsAllFields(): void
    {
        $audit = $this->makeAudit();
        $finding = $this->addFinding($audit, 'security.hardcoded-secret', FindingCategory::Security, FindingSeverity::Critical, 5, 0.9, 'src/config.php');

        $payload = $this->builder->finding($finding);

        self::assertSame((string) $finding->getId(), $payload['id']);
        self::assertSame('security.hardcoded-secret', $payload['rule_id']);
        self::assertSame('security', $payload['category']);
        self::assertSame('critical', $payload['severity']);
        self::assertSame('src/config.php', $payload['file_path']);
        self::assertSame(0.9, $payload['confidence']);
        self::assertSame(5, $payload['priority']);
    }

    public function testTopFindingsOrdersByPriorityThenConfidenceDescending(): void
    {
        $audit = $this->makeAudit();
        $low = $this->addFinding($audit, 'r1', FindingCategory::Security, FindingSeverity::Low, 2, 0.5);
        $highConfidence = $this->addFinding($audit, 'r2', FindingCategory::Security, FindingSeverity::High, 4, 0.9);
        $lowConfidence = $this->addFinding($audit, 'r3', FindingCategory::Security, FindingSeverity::High, 4, 0.6);

        $ordered = $this->builder->topFindings($audit);

        self::assertSame(['r2', 'r3', 'r1'], array_column($ordered, 'rule_id'));
        self::assertNotSame($low, $highConfidence);
    }

    public function testTopFindingsFiltersByCategory(): void
    {
        $audit = $this->makeAudit();
        $this->addFinding($audit, 'sec.1', FindingCategory::Security, FindingSeverity::High, 4, 0.8);
        $this->addFinding($audit, 'arch.1', FindingCategory::Architecture, FindingSeverity::Medium, 3, 0.7);

        $architectureOnly = $this->builder->topFindings($audit, FindingCategory::Architecture);

        self::assertCount(1, $architectureOnly);
        self::assertSame('arch.1', $architectureOnly[0]['rule_id']);
    }

    public function testTopFindingsCapsAtFiftyEntries(): void
    {
        $audit = $this->makeAudit();
        for ($i = 0; $i < 60; ++$i) {
            $this->addFinding($audit, "rule.$i", FindingCategory::Security, FindingSeverity::Low, 1, 0.5);
        }

        $result = $this->builder->topFindings($audit);

        self::assertCount(50, $result);
    }
}
