<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Analyzer;

final class JavaScriptAnalyzer extends AbstractRuleBasedAnalyzer
{
    private const EXTENSIONS = ['js', 'jsx', 'mjs', 'cjs'];

    public function getName(): string
    {
        return 'javascript';
    }

    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), self::EXTENSIONS, true);
    }
}
