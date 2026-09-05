<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scanning;

use App\Service\Scanning\BinaryFileDetector;
use App\Service\Scanning\LanguageDetector;
use App\Service\Scanning\LineCounter;
use App\Service\Scanning\RepositoryScanner;
use App\Service\Scanning\ScannerConfig;
use App\Service\Scanning\SensitiveFileGuard;
use PHPUnit\Framework\TestCase;

final class RepositoryScannerTest extends TestCase
{
    private function makeScanner(?ScannerConfig $config = null): RepositoryScanner
    {
        $config ??= new ScannerConfig(
            ignoredDirectories: ['.git', 'node_modules', 'vendor', 'dist', 'build', 'coverage'],
            ignoredFilePatterns: ['*.min.js', '*.map'],
            binaryExtensions: ['png', 'jpg', 'exe'],
            maxFileSizeBytes: 5242880,
            maxRepositorySizeBytes: 209715200,
            maxFiles: 20000,
        );

        return new RepositoryScanner(
            $config,
            new LanguageDetector(),
            new LineCounter(),
            new BinaryFileDetector($config),
            new SensitiveFileGuard(),
        );
    }

    private function fixtureRoot(): string
    {
        return __DIR__.'/../../../Fixtures/scanner/sample-repo';
    }

    public function testLanguageStatsMatchFixture(): void
    {
        $result = $this->makeScanner()->scan($this->fixtureRoot());

        self::assertArrayHasKey('TypeScript', $result->languageStats);
        self::assertSame(1, $result->languageStats['TypeScript']['files']);
        self::assertGreaterThan(0, $result->languageStats['TypeScript']['lines']);

        self::assertArrayHasKey('Python', $result->languageStats);
        // src/util.py + tests/test_util.py
        self::assertSame(2, $result->languageStats['Python']['files']);

        self::assertArrayHasKey('PHP', $result->languageStats);
        self::assertSame(1, $result->languageStats['PHP']['files']);
    }

    public function testIgnoredDirectoriesAreExcludedEntirely(): void
    {
        $result = $this->makeScanner()->scan($this->fixtureRoot());

        // Only the root-level bundle.min.js should be counted as a .js file;
        // node_modules/ignored.js and dist/bundle.min.js must never surface
        // because their parent directories are excluded from traversal entirely.
        self::assertSame(1, $result->extensionStats['js'] ?? 0);
    }

    public function testIgnoredFilePatternIsCountedButNotAnalyzed(): void
    {
        $result = $this->makeScanner()->scan($this->fixtureRoot());

        self::assertGreaterThanOrEqual(1, $result->ignoredFileCount);
    }

    public function testBinaryFileIsExcludedFromLanguageStats(): void
    {
        $result = $this->makeScanner()->scan($this->fixtureRoot());

        self::assertSame(1, $result->binaryFileCount);
    }

    public function testMetadataFilesAreDetected(): void
    {
        $result = $this->makeScanner()->scan($this->fixtureRoot());

        self::assertTrue($result->metadata['packageJson']);
        self::assertTrue($result->metadata['composerJson']);
        self::assertTrue($result->metadata['requirementsTxt']);
        self::assertTrue($result->metadata['pyprojectToml']);
        self::assertTrue($result->metadata['dockerfile']);
        self::assertTrue($result->metadata['dockerCompose']);
        self::assertTrue($result->metadata['readme']);
        self::assertTrue($result->metadata['ciConfig']);
        self::assertTrue($result->metadata['envFilePresent']);
        self::assertContains('tests', $result->metadata['testDirectories']);
    }

    public function testSensitiveEnvFileContentIsNeverExposed(): void
    {
        $result = $this->makeScanner()->scan($this->fixtureRoot());

        $encoded = json_encode($result->metadata).json_encode($result->languageStats).json_encode($result->extensionStats);
        self::assertStringNotContainsString('this-should-never-appear-in-scanner-output', (string) $encoded);
    }

    public function testNestedDirectoriesAreCounted(): void
    {
        $result = $this->makeScanner()->scan($this->fixtureRoot());

        self::assertGreaterThan(0, $result->totalDirectories);
    }

    public function testMaxFilesLimitTruncatesScanning(): void
    {
        $config = new ScannerConfig(
            ignoredDirectories: ['.git', 'node_modules', 'vendor', 'dist'],
            ignoredFilePatterns: [],
            binaryExtensions: [],
            maxFileSizeBytes: 5242880,
            maxRepositorySizeBytes: 209715200,
            maxFiles: 1,
        );
        $scanner = $this->makeScanner($config);

        $result = $scanner->scan($this->fixtureRoot());

        self::assertSame(1, $result->totalFiles);
        self::assertTrue($result->metadata['truncated']);
    }

    public function testSymbolicLinkIsNotFollowed(): void
    {
        $linkPath = $this->fixtureRoot().'/link-to-src';
        $created = @symlink($this->fixtureRoot().'/src', $linkPath);
        if (!$created) {
            self::markTestSkipped('Creating symbolic links is not permitted in this environment.');
        }

        try {
            $result = $this->makeScanner()->scan($this->fixtureRoot());

            // The three real files under src/ are counted once each; the symlinked
            // copy must not double them.
            self::assertSame(1, $result->languageStats['TypeScript']['files']);
        } finally {
            @unlink($linkPath);
        }
    }
}
