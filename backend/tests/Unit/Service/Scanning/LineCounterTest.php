<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scanning;

use App\Service\Scanning\LineCounter;
use PHPUnit\Framework\TestCase;

final class LineCounterTest extends TestCase
{
    private LineCounter $counter;
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->counter = new LineCounter();
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'line-counter-test-');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpFile);
    }

    public function testCountsLinesWithTrailingNewline(): void
    {
        file_put_contents($this->tmpFile, "one\ntwo\nthree\n");
        self::assertSame(3, $this->counter->count($this->tmpFile));
    }

    public function testCountsFinalLineWithoutTrailingNewline(): void
    {
        file_put_contents($this->tmpFile, "one\ntwo\nthree");
        self::assertSame(3, $this->counter->count($this->tmpFile));
    }

    public function testEmptyFileHasZeroLines(): void
    {
        file_put_contents($this->tmpFile, '');
        self::assertSame(0, $this->counter->count($this->tmpFile));
    }

    public function testLargeFileIsCountedWithoutLoadingItAllAtOnce(): void
    {
        $handle = fopen($this->tmpFile, 'wb');
        for ($i = 0; $i < 100000; ++$i) {
            fwrite($handle, "line {$i}\n");
        }
        fclose($handle);

        self::assertSame(100000, $this->counter->count($this->tmpFile));
    }
}
