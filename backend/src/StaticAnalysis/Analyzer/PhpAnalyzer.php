<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Analyzer;

final class PhpAnalyzer extends AbstractRuleBasedAnalyzer
{
    public function getName(): string
    {
        return 'php';
    }

    public function supports(string $extension): bool
    {
        return 'php' === strtolower($extension);
    }
}
