<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Rule\Quality;

use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use App\StaticAnalysis\Finding;
use App\StaticAnalysis\Rule\RuleInterface;
use App\StaticAnalysis\Support\SourceFile;

final class LongFileRule implements RuleInterface
{
    // Documented per-language thresholds (see docs/static-analysis.md).
    private const THRESHOLDS = [
        'javascript' => 500,
        'typescript' => 500,
        'php' => 500,
        'python' => 600,
    ];

    public function getRuleId(): string
    {
        return 'quality.long-file';
    }

    public function getCategory(): FindingCategory
    {
        return FindingCategory::CodeQuality;
    }

    public function supportedAnalyzers(): array
    {
        return array_keys(self::THRESHOLDS);
    }

    public function evaluate(SourceFile $file, string $analyzer): array
    {
        $threshold = self::THRESHOLDS[$analyzer] ?? null;
        if (null === $threshold) {
            return [];
        }

        $nonBlankLines = 0;
        for ($lineNumber = 1; $lineNumber <= $file->lineCount(); ++$lineNumber) {
            if ('' !== trim($file->lines[$lineNumber])) {
                ++$nonBlankLines;
            }
        }

        if ($nonBlankLines <= $threshold) {
            return [];
        }

        return [new Finding(
            ruleId: $this->getRuleId(),
            category: $this->getCategory(),
            severity: FindingSeverity::Low,
            title: 'Extremely long file',
            description: sprintf('This file has %d non-blank lines, well beyond the %d-line guideline for %s files.', $nonBlankLines, $threshold, $analyzer),
            filePath: $file->relativePath,
            startLine: 1,
            endLine: $file->lineCount(),
            recommendation: 'Consider splitting this file into smaller, more focused modules.',
            confidence: 1.0,
            analyzer: $analyzer,
            metadata: ['lineCount' => $nonBlankLines, 'threshold' => $threshold],
        )];
    }
}
