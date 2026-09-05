<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Rule;

use App\Entity\Enum\FindingCategory;
use App\StaticAnalysis\Finding;
use App\StaticAnalysis\Support\SourceFile;

/**
 * A per-file rule. Implementations should be conservative: if a rule cannot
 * confidently detect a problem, it must lower confidence or emit nothing at
 * all (see StaticAnalysisEngine::MIN_CONFIDENCE).
 */
interface RuleInterface
{
    public function getRuleId(): string;

    public function getCategory(): FindingCategory;

    /** @return string[] analyzer names this rule applies to, e.g. ['javascript', 'typescript'] */
    public function supportedAnalyzers(): array;

    /** @return Finding[] */
    public function evaluate(SourceFile $file, string $analyzer): array;
}
