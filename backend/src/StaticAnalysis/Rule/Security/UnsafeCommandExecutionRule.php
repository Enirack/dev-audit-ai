<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Rule\Security;

use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use App\StaticAnalysis\Finding;
use App\StaticAnalysis\Rule\RuleInterface;
use App\StaticAnalysis\Support\SourceFile;

final class UnsafeCommandExecutionRule implements RuleInterface
{
    private const PATTERNS = [
        'javascript' => '/\b(?:child_process\.)?(exec|execSync)\s*\(/',
        'typescript' => '/\b(?:child_process\.)?(exec|execSync)\s*\(/',
        'php' => '/\b(exec|shell_exec|system|passthru|proc_open)\s*\(/',
        'python' => '/\bos\.system\s*\(|subprocess\.(call|run|Popen)\s*\([^)]*shell\s*=\s*True/',
    ];

    private const INTERPOLATION_HINTS = ['${', '" .', '. \'', '.concat(', 'f"', 'f\'', '.format(', '% ('];

    public function getRuleId(): string
    {
        return 'security.unsafe-command-execution';
    }

    public function getCategory(): FindingCategory
    {
        return FindingCategory::Security;
    }

    public function supportedAnalyzers(): array
    {
        return array_keys(self::PATTERNS);
    }

    public function evaluate(SourceFile $file, string $analyzer): array
    {
        $pattern = self::PATTERNS[$analyzer] ?? null;
        if (null === $pattern) {
            return [];
        }

        $findings = [];

        for ($lineNumber = 1; $lineNumber <= $file->lineCount(); ++$lineNumber) {
            if ($file->isFullLineComment($lineNumber)) {
                continue;
            }

            $line = $file->lines[$lineNumber];
            if (1 !== preg_match($pattern, $line)) {
                continue;
            }

            $hasInterpolation = false;
            foreach (self::INTERPOLATION_HINTS as $hint) {
                if (str_contains($line, $hint)) {
                    $hasInterpolation = true;
                    break;
                }
            }

            $confidence = $hasInterpolation ? 0.9 : 0.4;

            $findings[] = new Finding(
                ruleId: $this->getRuleId(),
                category: $this->getCategory(),
                severity: $hasInterpolation ? FindingSeverity::High : FindingSeverity::Medium,
                title: 'Shell command execution',
                description: $hasInterpolation
                    ? 'A shell command is built by concatenating or interpolating a variable into it, which is a classic command-injection pattern if any part comes from outside input.'
                    : 'A shell command is executed here. This is not necessarily unsafe, but deserves a check that no untrusted input reaches it.',
                filePath: $file->relativePath,
                startLine: $lineNumber,
                endLine: $lineNumber,
                recommendation: 'Prefer APIs that take arguments as an array (e.g. execFile, Symfony Process with an argument array, subprocess with a list and shell=False) instead of building a shell string.',
                confidence: $confidence,
                analyzer: $analyzer,
            );
        }

        return $findings;
    }
}
