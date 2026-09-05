<?php

declare(strict_types=1);

namespace App\Tests\Unit\StaticAnalysis\Rule\Testing;

use App\Service\Scanning\RepositoryScanResult;
use App\StaticAnalysis\RepositoryAnalysisContext;
use App\StaticAnalysis\Rule\Testing\MissingTestDirectoryRule;
use PHPUnit\Framework\TestCase;

final class MissingTestDirectoryRuleTest extends TestCase
{
    private MissingTestDirectoryRule $rule;

    protected function setUp(): void
    {
        $this->rule = new MissingTestDirectoryRule();
    }

    public function testRepositoryWithSourceButNoTestsIsFlagged(): void
    {
        $context = $this->makeContext(
            languageStats: ['TypeScript' => ['files' => 5, 'lines' => 500]],
            testDirectories: [],
        );

        $findings = $this->rule->evaluate($context);

        self::assertCount(1, $findings);
        self::assertSame('testing.missing-test-directory', $findings[0]->ruleId);
    }

    public function testRepositoryWithTestDirectoryIsNotFlagged(): void
    {
        $context = $this->makeContext(
            languageStats: ['TypeScript' => ['files' => 5, 'lines' => 500]],
            testDirectories: ['tests'],
        );

        self::assertSame([], $this->rule->evaluate($context));
    }

    public function testEmptyRepositoryIsNotFlagged(): void
    {
        $context = $this->makeContext(languageStats: [], testDirectories: []);

        self::assertSame([], $this->rule->evaluate($context));
    }

    /**
     * @param array<string, array{files: int, lines: int}> $languageStats
     * @param string[]                                       $testDirectories
     */
    private function makeContext(array $languageStats, array $testDirectories): RepositoryAnalysisContext
    {
        $scanResult = new RepositoryScanResult(
            totalFiles: array_sum(array_column($languageStats, 'files')),
            totalDirectories: 1,
            totalSizeBytes: 1000,
            binaryFileCount: 0,
            ignoredFileCount: 0,
            languageStats: $languageStats,
            extensionStats: [],
            metadata: ['testDirectories' => $testDirectories],
        );

        return new RepositoryAnalysisContext('/tmp/does-not-matter', $scanResult);
    }
}
