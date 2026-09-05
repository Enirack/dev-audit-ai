<?php

declare(strict_types=1);

namespace App\Tests\Unit\StaticAnalysis\Rule\Quality;

use App\StaticAnalysis\Rule\Quality\LongFileRule;
use App\StaticAnalysis\Support\SourceFile;
use PHPUnit\Framework\TestCase;

final class LongFileRuleTest extends TestCase
{
    private LongFileRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LongFileRule();
    }

    public function testFileOverThresholdIsFlagged(): void
    {
        $contents = str_repeat("const x = 1;\n", 501);
        $file = new SourceFile('big.ts', $contents);

        $findings = $this->rule->evaluate($file, 'typescript');

        self::assertCount(1, $findings);
        self::assertSame(1.0, $findings[0]->confidence);
    }

    public function testFileUnderThresholdIsNotFlagged(): void
    {
        $contents = str_repeat("const x = 1;\n", 10);
        $file = new SourceFile('small.ts', $contents);

        self::assertSame([], $this->rule->evaluate($file, 'typescript'));
    }

    public function testBlankLinesDoNotCountTowardTheThreshold(): void
    {
        $contents = str_repeat("\n", 600);
        $file = new SourceFile('mostly-blank.ts', $contents);

        self::assertSame([], $this->rule->evaluate($file, 'typescript'));
    }

    public function testPythonHasItsOwnHigherThreshold(): void
    {
        $contents = str_repeat("x = 1\n", 550);
        $file = new SourceFile('big.py', $contents);

        // Below the Python-specific 600-line threshold, even though it would
        // exceed the 500-line JS/TS/PHP threshold.
        self::assertSame([], $this->rule->evaluate($file, 'python'));
    }
}
