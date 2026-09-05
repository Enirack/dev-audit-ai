<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Rule\Quality;

use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use App\StaticAnalysis\Finding;
use App\StaticAnalysis\Rule\RuleInterface;
use App\StaticAnalysis\Support\SourceFile;

final class TodoFixmeAccumulationRule implements RuleInterface
{
    private const PATTERN = '/\b(TODO|FIXME|HACK|XXX)\b/i';
    private const THRESHOLD = 5;

    public function getRuleId(): string
    {
        return 'quality.todo-fixme-accumulation';
    }

    public function getCategory(): FindingCategory
    {
        return FindingCategory::CodeQuality;
    }

    public function supportedAnalyzers(): array
    {
        return ['javascript', 'typescript', 'php', 'python'];
    }

    public function evaluate(SourceFile $file, string $analyzer): array
    {
        $count = 0;
        for ($lineNumber = 1; $lineNumber <= $file->lineCount(); ++$lineNumber) {
            $count += preg_match_all(self::PATTERN, $file->lines[$lineNumber]);
        }

        if ($count < self::THRESHOLD) {
            return [];
        }

        return [new Finding(
            ruleId: $this->getRuleId(),
            category: $this->getCategory(),
            severity: FindingSeverity::Info,
            title: 'Accumulation of TODO/FIXME markers',
            description: sprintf('%d TODO/FIXME/HACK/XXX markers found in this file.', $count),
            filePath: $file->relativePath,
            startLine: null,
            endLine: null,
            recommendation: 'Track these in the issue tracker and resolve or remove stale markers.',
            confidence: 1.0,
            analyzer: $analyzer,
            metadata: ['count' => $count],
        )];
    }
}
