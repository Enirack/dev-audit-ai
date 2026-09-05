<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scanning;

use App\Service\Scanning\LanguageDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LanguageDetectorTest extends TestCase
{
    private LanguageDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new LanguageDetector();
    }

    #[DataProvider('extensions')]
    public function testDetect(string $extension, ?string $expected): void
    {
        self::assertSame($expected, $this->detector->detect($extension));
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function extensions(): iterable
    {
        yield 'js' => ['js', 'JavaScript'];
        yield 'jsx' => ['jsx', 'JavaScript'];
        yield 'ts' => ['ts', 'TypeScript'];
        yield 'tsx' => ['tsx', 'TypeScript'];
        yield 'php' => ['php', 'PHP'];
        yield 'py' => ['py', 'Python'];
        yield 'html' => ['html', 'HTML'];
        yield 'css' => ['css', 'CSS'];
        yield 'json' => ['json', 'JSON'];
        yield 'yaml' => ['yaml', 'YAML'];
        yield 'yml' => ['yml', 'YAML'];
        yield 'md' => ['md', 'Markdown'];
        yield 'uppercase extension' => ['TS', 'TypeScript'];
        yield 'unknown extension' => ['bin', null];
        yield 'empty extension' => ['', null];
    }
}
