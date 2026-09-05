<?php

declare(strict_types=1);

namespace App\Tests\Unit\StaticAnalysis\Rule\Security;

use App\StaticAnalysis\Rule\Security\DangerousEvalRule;
use App\StaticAnalysis\Support\SourceFile;
use PHPUnit\Framework\TestCase;

final class DangerousEvalRuleTest extends TestCase
{
    private DangerousEvalRule $rule;

    protected function setUp(): void
    {
        $this->rule = new DangerousEvalRule();
    }

    public function testPhpEvalIsFlagged(): void
    {
        $file = new SourceFile('legacy.php', "<?php\neval(\$userInput);\n");

        $findings = $this->rule->evaluate($file, 'php');

        self::assertCount(1, $findings);
        self::assertSame('security.dangerous-eval', $findings[0]->ruleId);
        self::assertSame(2, $findings[0]->startLine);
    }

    public function testPythonExecIsFlagged(): void
    {
        $file = new SourceFile('script.py', "exec(compile(user_code, '<string>', 'exec'))\n");

        self::assertCount(1, $this->rule->evaluate($file, 'python'));
    }

    public function testJavaScriptNewFunctionIsFlagged(): void
    {
        $file = new SourceFile('app.js', "const fn = new Function('a', 'b', 'return a + b');\n");

        self::assertCount(1, $this->rule->evaluate($file, 'javascript'));
    }

    public function testCommentedOutEvalIsNotFlagged(): void
    {
        $file = new SourceFile('app.js', "// eval(someString);\n");

        self::assertSame([], $this->rule->evaluate($file, 'javascript'));
    }

    public function testUnrelatedFunctionCallIsNotFlagged(): void
    {
        $file = new SourceFile('app.js', "evaluate(someString);\n");

        self::assertSame([], $this->rule->evaluate($file, 'javascript'));
    }
}
