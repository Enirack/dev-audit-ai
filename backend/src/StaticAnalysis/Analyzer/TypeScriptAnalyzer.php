<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Analyzer;

final class TypeScriptAnalyzer extends AbstractRuleBasedAnalyzer
{
    private const EXTENSIONS = ['ts', 'tsx'];

    public function getName(): string
    {
        return 'typescript';
    }

    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), self::EXTENSIONS, true);
    }
}
