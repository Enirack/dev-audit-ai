<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Analyzer;

final class PythonAnalyzer extends AbstractRuleBasedAnalyzer
{
    private const EXTENSIONS = ['py', 'pyi'];

    public function getName(): string
    {
        return 'python';
    }

    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), self::EXTENSIONS, true);
    }
}
