<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Rule\Testing;

use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use App\StaticAnalysis\Finding;
use App\StaticAnalysis\RepositoryAnalysisContext;
use App\StaticAnalysis\Rule\RepositoryRuleInterface;

final class MissingTestDirectoryRule implements RepositoryRuleInterface
{
    public function getRuleId(): string
    {
        return 'testing.missing-test-directory';
    }

    public function evaluate(RepositoryAnalysisContext $context): array
    {
        $hasSourceFiles = array_sum(array_map(
            static fn (array $stats): int => $stats['files'],
            $context->scanResult->languageStats,
        )) > 0;

        $testDirectories = $context->scanResult->metadata['testDirectories'] ?? [];

        if (!$hasSourceFiles || [] !== $testDirectories) {
            return [];
        }

        return [new Finding(
            ruleId: $this->getRuleId(),
            category: FindingCategory::Testing,
            severity: FindingSeverity::High,
            title: 'No test directory found',
            description: 'This repository contains source files but no conventional test directory (tests/, test/, __tests__/, spec/) was detected.',
            filePath: null,
            startLine: null,
            endLine: null,
            recommendation: 'Add a test suite. Even a small one substantially reduces regression risk.',
            confidence: 1.0,
            analyzer: 'repository',
        )];
    }
}
