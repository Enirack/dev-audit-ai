<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Rule;

use App\StaticAnalysis\Finding;
use App\StaticAnalysis\RepositoryAnalysisContext;

/**
 * A repository-wide rule: runs once per audit against metadata/inventory
 * rather than per-file (e.g. missing test directories, excessive
 * dependencies).
 */
interface RepositoryRuleInterface
{
    public function getRuleId(): string;

    /** @return Finding[] */
    public function evaluate(RepositoryAnalysisContext $context): array;
}
