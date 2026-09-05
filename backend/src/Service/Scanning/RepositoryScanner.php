<?php

declare(strict_types=1);

namespace App\Service\Scanning;

use Symfony\Component\Finder\Finder;

/**
 * Recursively inspects an already-ingested repository workspace and builds a
 * structured, deterministic inventory. Never executes anything found in the
 * repository; only reads file metadata and, for text files under the size
 * limit, streams their contents to count lines.
 */
final class RepositoryScanner
{
    public function __construct(
        private readonly ScannerConfig $config,
        private readonly LanguageDetector $languageDetector,
        private readonly LineCounter $lineCounter,
        private readonly BinaryFileDetector $binaryFileDetector,
        private readonly SensitiveFileGuard $sensitiveFileGuard,
    ) {
    }

    public function scan(string $root): RepositoryScanResult
    {
        $metadataDetector = new RepositoryMetadataDetector();

        $totalFiles = 0;
        $totalDirectories = 0;
        $totalSizeBytes = 0;
        $binaryFileCount = 0;
        $ignoredFileCount = 0;
        $truncated = false;

        /** @var array<string, array{files: int, lines: int}> $languageStats */
        $languageStats = [];
        /** @var array<string, int> $extensionStats */
        $extensionStats = [];

        $finder = Finder::create()
            ->in($root)
            ->ignoreDotFiles(false)
            ->ignoreVCS(true)
            ->exclude($this->config->ignoredDirectories);

        foreach ($finder as $fileInfo) {
            $relativePath = str_replace('\\', '/', $fileInfo->getRelativePathname());

            if ($fileInfo->isDir()) {
                ++$totalDirectories;
                $metadataDetector->observe($relativePath, true);
                continue;
            }

            if ($totalFiles >= $this->config->maxFiles || $totalSizeBytes >= $this->config->maxRepositorySizeBytes) {
                $truncated = true;
                break;
            }

            ++$totalFiles;
            $totalSizeBytes += $fileInfo->getSize();
            $metadataDetector->observe($relativePath, false);

            $filename = $fileInfo->getFilename();
            $extension = strtolower($fileInfo->getExtension());
            $extensionStats[$extension] = ($extensionStats[$extension] ?? 0) + 1;

            if ($this->matchesIgnoredPattern($filename)) {
                ++$ignoredFileCount;
                continue;
            }

            if ($this->sensitiveFileGuard->isSensitiveEnvFile($filename)) {
                // Never read the contents of a real (non-template) env file.
                continue;
            }

            $absolutePath = $fileInfo->getPathname();
            $isBinary = $this->binaryFileDetector->isBinary($absolutePath, $extension);
            if ($isBinary) {
                ++$binaryFileCount;
                continue;
            }

            $language = $this->languageDetector->detect($extension);
            if (null === $language) {
                continue;
            }

            if ($fileInfo->getSize() > $this->config->maxFileSizeBytes) {
                continue; // too large to safely stream-count within this pass
            }

            $lines = $this->lineCounter->count($absolutePath);
            $languageStats[$language] ??= ['files' => 0, 'lines' => 0];
            ++$languageStats[$language]['files'];
            $languageStats[$language]['lines'] += $lines;
        }

        $metadata = $metadataDetector->getMetadata();
        $metadata['testDirectories'] = $metadataDetector->getTestDirectories();
        $metadata['truncated'] = $truncated;

        return new RepositoryScanResult(
            totalFiles: $totalFiles,
            totalDirectories: $totalDirectories,
            totalSizeBytes: $totalSizeBytes,
            binaryFileCount: $binaryFileCount,
            ignoredFileCount: $ignoredFileCount,
            languageStats: $languageStats,
            extensionStats: $extensionStats,
            metadata: $metadata,
        );
    }

    private function matchesIgnoredPattern(string $filename): bool
    {
        foreach ($this->config->ignoredFilePatterns as $pattern) {
            if (fnmatch($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }
}
