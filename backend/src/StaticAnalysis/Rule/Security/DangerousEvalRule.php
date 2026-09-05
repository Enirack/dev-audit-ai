<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Rule\Security;

use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use App\StaticAnalysis\Finding;
use App\StaticAnalysis\Rule\RuleInterface;
use App\StaticAnalysis\Support\SourceFile;

final class DangerousEvalRule implements RuleInterface
{
    private const PATTERNS = [
        'javascript' => '/\b(eval|new\s+Function)\s*\(/',
        'typescript' => '/\b(eval|new\s+Function)\s*\(/',
        'php' => '/\beval\s*\(/',
        'python' => '/\b(eval|exec)\s*\(/',
    ];

    public function getRuleId(): string
    {
        return 'security.dangerous-eval';
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

            if (1 !== preg_match($pattern, $file->lines[$lineNumber])) {
                continue;
            }

            $findings[] = new Finding(
                ruleId: $this->getRuleId(),
                category: $this->getCategory(),
                severity: FindingSeverity::High,
                title: 'Dynamic code execution',
                description: 'Dynamically evaluating a string as code makes it very easy to introduce remote code execution if any part of the input is influenced by a user.',
                filePath: $file->relativePath,
                startLine: $lineNumber,
                endLine: $lineNumber,
                recommendation: 'Avoid eval()/exec()/new Function(); use safer alternatives (JSON.parse, explicit dispatch tables, etc.).',
                confidence: 0.9,
                analyzer: $analyzer,
            );
        }

        return $findings;
    }
}
