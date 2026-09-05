<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Analyzer;

use App\StaticAnalysis\Finding;
use App\StaticAnalysis\Support\SourceFile;

interface AnalyzerInterface
{
    public function getName(): string;

    public function supports(string $extension): bool;

    /** @return Finding[] */
    public function analyze(SourceFile $file): array;
}
