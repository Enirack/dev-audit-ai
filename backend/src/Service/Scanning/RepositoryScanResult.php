<?php

declare(strict_types=1);

namespace App\Service\Scanning;

final readonly class RepositoryScanResult
{
    /**
     * @param array<string, array{files: int, lines: int}> $languageStats
     * @param array<string, int>                            $extensionStats
     * @param array<string, mixed>                           $metadata
     */
    public function __construct(
        public int $totalFiles,
        public int $totalDirectories,
        public int $totalSizeBytes,
        public int $binaryFileCount,
        public int $ignoredFileCount,
        public array $languageStats,
        public array $extensionStats,
        public array $metadata,
    ) {
    }
}
