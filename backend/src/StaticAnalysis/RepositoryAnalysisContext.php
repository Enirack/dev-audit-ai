<?php

declare(strict_types=1);

namespace App\StaticAnalysis;

use App\Service\Scanning\RepositoryScanResult;

/**
 * Everything a repository-wide rule needs: the Phase 3 scan result plus the
 * ingested workspace root, so a rule may read a specific known metadata file
 * (e.g. package.json) directly, without the scanner having to store file
 * contents wholesale.
 */
final readonly class RepositoryAnalysisContext
{
    public function __construct(
        public string $workspaceRoot,
        public RepositoryScanResult $scanResult,
    ) {
    }

    public function readFileIfExists(string $relativePath): ?string
    {
        $path = rtrim($this->workspaceRoot, '/').'/'.ltrim($relativePath, '/');
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return false === $contents ? null : $contents;
    }
}
