<?php

declare(strict_types=1);

namespace App\Service\Scanning;

final readonly class ScannerConfig
{
    /**
     * @param string[] $ignoredDirectories
     * @param string[] $ignoredFilePatterns
     * @param string[] $binaryExtensions
     */
    public function __construct(
        public array $ignoredDirectories,
        public array $ignoredFilePatterns,
        public array $binaryExtensions,
        public int $maxFileSizeBytes,
        public int $maxRepositorySizeBytes,
        public int $maxFiles,
    ) {
    }
}
