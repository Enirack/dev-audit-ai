<?php

declare(strict_types=1);

namespace App\Tests\Unit\StaticAnalysis\Rule\Quality;

use App\StaticAnalysis\Rule\Quality\TodoFixmeAccumulationRule;
use App\StaticAnalysis\Support\SourceFile;
use PHPUnit\Framework\TestCase;

final class TodoFixmeAccumulationRuleTest extends TestCase
{
    private TodoFixmeAccumulationRule $rule;

    protected function setUp(): void
    {
        $this->rule = new TodoFixmeAccumulationRule();
    }

    public function testFiveOrMoreMarkersAreFlagged(): void
    {
        $contents = implode("\n", [
            '// TODO: fix this',
            '// FIXME: and this',
            '// TODO: also this',
            '// HACK: ugly workaround',
            '// XXX: revisit',
        ]);
        $file = new SourceFile('legacy.js', $contents);

        $findings = $this->rule->evaluate($file, 'javascript');

        self::assertCount(1, $findings);
        self::assertSame(5, $findings[0]->metadata['count']);
    }

    public function testFewMarkersAreNotFlagged(): void
    {
        $file = new SourceFile('clean.js', "// TODO: one small thing\n");

        self::assertSame([], $this->rule->evaluate($file, 'javascript'));
    }

    public function testNoMarkersAreNotFlagged(): void
    {
        $file = new SourceFile('clean.js', "const x = 1;\n");

        self::assertSame([], $this->rule->evaluate($file, 'javascript'));
    }
}
