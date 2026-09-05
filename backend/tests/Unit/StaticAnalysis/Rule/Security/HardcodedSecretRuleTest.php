<?php

declare(strict_types=1);

namespace App\Tests\Unit\StaticAnalysis\Rule\Security;

use App\StaticAnalysis\Rule\Security\HardcodedSecretRule;
use App\StaticAnalysis\Support\SourceFile;
use PHPUnit\Framework\TestCase;

final class HardcodedSecretRuleTest extends TestCase
{
    private HardcodedSecretRule $rule;

    protected function setUp(): void
    {
        $this->rule = new HardcodedSecretRule();
    }

    public function testHighEntropyLiteralIsFlagged(): void
    {
        $file = new SourceFile('config.js', "const apiKey = 'sK9x2Lm4qR7vT1zN8pW3jH6bC5dF0yA';\n");

        $findings = $this->rule->evaluate($file, 'javascript');

        self::assertCount(1, $findings);
        self::assertSame('security.hardcoded-secret', $findings[0]->ruleId);
        self::assertGreaterThanOrEqual(0.8, $findings[0]->confidence);
    }

    public function testPlaceholderValueIsNotFlagged(): void
    {
        $file = new SourceFile('config.js', "const apiKey = 'changeme';\nconst secret = 'your-secret-here';\n");

        self::assertSame([], $this->rule->evaluate($file, 'javascript'));
    }

    public function testEnvVariableReferenceIsNotFlagged(): void
    {
        $file = new SourceFile('config.php', "\$secret = getenv('APP_SECRET');\n");

        self::assertSame([], $this->rule->evaluate($file, 'php'));
    }

    public function testCommentedOutLineIsNotFlagged(): void
    {
        $file = new SourceFile('config.py', "# password = 'aVeryRealLookingSecretValue123456'\n");

        self::assertSame([], $this->rule->evaluate($file, 'python'));
    }

    public function testUnrelatedCodeIsNotFlagged(): void
    {
        $file = new SourceFile('app.ts', "export function add(a: number, b: number): number {\n  return a + b;\n}\n");

        self::assertSame([], $this->rule->evaluate($file, 'typescript'));
    }
}
