<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Rule\Security;

use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use App\StaticAnalysis\Finding;
use App\StaticAnalysis\Rule\RuleInterface;
use App\StaticAnalysis\Support\SourceFile;

/**
 * Flags assignments that look like a hardcoded credential with a
 * high-entropy literal value. Narrow on purpose: a wide net here produces
 * mostly noise (config keys, translation strings, test fixtures).
 */
final class HardcodedSecretRule implements RuleInterface
{
    private const PATTERN = '/(?i)\b(api[_-]?key|secret|token|password|passwd|access[_-]?key)\b\s*[:=]\s*[\'"]([A-Za-z0-9+\/_\-]{16,})[\'"]/';

    private const PLACEHOLDER_PATTERN = '/(?i)^(changeme|xxx+|example|test|dummy|placeholder|your[_-]?.*|<.*>|\$\{.*\}|%env\(.*\)%)$/';

    public function getRuleId(): string
    {
        return 'security.hardcoded-secret';
    }

    public function getCategory(): FindingCategory
    {
        return FindingCategory::Security;
    }

    public function supportedAnalyzers(): array
    {
        return ['javascript', 'typescript', 'php', 'python'];
    }

    public function evaluate(SourceFile $file, string $analyzer): array
    {
        $findings = [];

        for ($lineNumber = 1; $lineNumber <= $file->lineCount(); ++$lineNumber) {
            if ($file->isFullLineComment($lineNumber)) {
                continue;
            }

            $line = $file->lines[$lineNumber];
            if (1 !== preg_match(self::PATTERN, $line, $matches)) {
                continue;
            }

            $value = $matches[2];
            if (1 === preg_match(self::PLACEHOLDER_PATTERN, $value)) {
                continue;
            }

            $entropy = $this->shannonEntropy($value);
            $confidence = $entropy >= 3.5 && strlen($value) >= 24 ? 0.85 : 0.55;

            $findings[] = new Finding(
                ruleId: $this->getRuleId(),
                category: $this->getCategory(),
                severity: $confidence >= 0.8 ? FindingSeverity::Critical : FindingSeverity::High,
                title: 'Potential hardcoded secret',
                description: sprintf('A variable named "%s" is assigned what looks like a real credential value directly in source code.', trim($matches[1])),
                filePath: $file->relativePath,
                startLine: $lineNumber,
                endLine: $lineNumber,
                recommendation: 'Move this value to an environment variable or a secret manager and load it at runtime instead of hardcoding it.',
                confidence: $confidence,
                analyzer: $analyzer,
            );
        }

        return $findings;
    }

    private function shannonEntropy(string $value): float
    {
        $frequencies = array_count_values(str_split($value));
        $length = strlen($value);
        $entropy = 0.0;

        foreach ($frequencies as $count) {
            $probability = $count / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }
}
