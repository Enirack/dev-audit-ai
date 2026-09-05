<?php

declare(strict_types=1);

namespace App\Tests\Unit\StaticAnalysis\Rule\Security;

use App\StaticAnalysis\Rule\Security\UnsafeCommandExecutionRule;
use App\StaticAnalysis\Support\SourceFile;
use PHPUnit\Framework\TestCase;

final class UnsafeCommandExecutionRuleTest extends TestCase
{
    private UnsafeCommandExecutionRule $rule;

    protected function setUp(): void
    {
        $this->rule = new UnsafeCommandExecutionRule();
    }

    public function testInterpolatedShellCommandIsHighConfidence(): void
    {
        $file = new SourceFile('deploy.php', "<?php\nexec(\"rm -rf \" . \$userPath);\n");

        $findings = $this->rule->evaluate($file, 'php');

        self::assertCount(1, $findings);
        self::assertGreaterThanOrEqual(0.9, $findings[0]->confidence);
    }

    public function testStaticShellCommandIsLowConfidence(): void
    {
        $file = new SourceFile('deploy.php', "<?php\nexec('ls -la');\n");

        $findings = $this->rule->evaluate($file, 'php');

        self::assertCount(1, $findings);
        self::assertLessThan(0.5, $findings[0]->confidence);
    }

    public function testPythonSubprocessWithShellTrueIsFlagged(): void
    {
        $file = new SourceFile('script.py', "subprocess.run(f\"echo {name}\", shell=True)\n");

        self::assertCount(1, $this->rule->evaluate($file, 'python'));
    }

    public function testExecFileWithArgumentArrayIsNotFlagged(): void
    {
        $file = new SourceFile('deploy.js', "child_process.execFile('ls', ['-la']);\n");

        self::assertSame([], $this->rule->evaluate($file, 'javascript'));
    }
}
