<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scanning;

use App\Service\Scanning\BinaryFileDetector;
use App\Service\Scanning\ScannerConfig;
use PHPUnit\Framework\TestCase;

final class BinaryFileDetectorTest extends TestCase
{
    private BinaryFileDetector $detector;
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->detector = new BinaryFileDetector(new ScannerConfig(
            ignoredDirectories: [],
            ignoredFilePatterns: [],
            binaryExtensions: ['png', 'exe'],
            maxFileSizeBytes: 1000000,
            maxRepositorySizeBytes: 1000000,
            maxFiles: 1000,
        ));
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'binary-test-');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpFile);
    }

    public function testKnownBinaryExtensionIsDetectedWithoutReadingContent(): void
    {
        file_put_contents($this->tmpFile, 'this is actually plain text');
        self::assertTrue($this->detector->isBinary($this->tmpFile, 'png'));
    }

    public function testNullByteContentIsDetectedAsBinary(): void
    {
        file_put_contents($this->tmpFile, "some\x00bytes");
        self::assertTrue($this->detector->isBinary($this->tmpFile, 'bin'));
    }

    public function testPlainTextIsNotBinary(): void
    {
        file_put_contents($this->tmpFile, "just plain text\nwith lines\n");
        self::assertFalse($this->detector->isBinary($this->tmpFile, 'txt'));
    }
}
