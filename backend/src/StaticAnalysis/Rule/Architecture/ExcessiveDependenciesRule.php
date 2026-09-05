<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Rule\Architecture;

use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use App\StaticAnalysis\Finding;
use App\StaticAnalysis\RepositoryAnalysisContext;
use App\StaticAnalysis\Rule\RepositoryRuleInterface;

final class ExcessiveDependenciesRule implements RepositoryRuleInterface
{
    // Documented per-ecosystem thresholds (see docs/static-analysis.md).
    private const NPM_THRESHOLD = 80;
    private const COMPOSER_THRESHOLD = 40;
    private const PIP_THRESHOLD = 50;

    public function getRuleId(): string
    {
        return 'architecture.excessive-dependencies';
    }

    public function evaluate(RepositoryAnalysisContext $context): array
    {
        $findings = [];

        $packageJson = $context->readFileIfExists('package.json');
        if (null !== $packageJson) {
            $count = $this->countNpmDependencies($packageJson);
            if ($count > self::NPM_THRESHOLD) {
                $findings[] = $this->makeFinding('package.json', $count, self::NPM_THRESHOLD, 'npm');
            }
        }

        $composerJson = $context->readFileIfExists('composer.json');
        if (null !== $composerJson) {
            $count = $this->countComposerDependencies($composerJson);
            if ($count > self::COMPOSER_THRESHOLD) {
                $findings[] = $this->makeFinding('composer.json', $count, self::COMPOSER_THRESHOLD, 'Composer');
            }
        }

        $requirementsTxt = $context->readFileIfExists('requirements.txt');
        if (null !== $requirementsTxt) {
            $count = $this->countPipDependencies($requirementsTxt);
            if ($count > self::PIP_THRESHOLD) {
                $findings[] = $this->makeFinding('requirements.txt', $count, self::PIP_THRESHOLD, 'pip');
            }
        }

        return $findings;
    }

    private function countNpmDependencies(string $contents): int
    {
        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return 0;
        }

        return count($data['dependencies'] ?? []) + count($data['devDependencies'] ?? []);
    }

    private function countComposerDependencies(string $contents): int
    {
        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return 0;
        }

        return count($data['require'] ?? []) + count($data['require-dev'] ?? []);
    }

    private function countPipDependencies(string $contents): int
    {
        $lines = array_filter(
            array_map('trim', explode("\n", $contents)),
            static fn (string $line): bool => '' !== $line && !str_starts_with($line, '#'),
        );

        return count($lines);
    }

    private function makeFinding(string $manifestFile, int $count, int $threshold, string $ecosystem): Finding
    {
        return new Finding(
            ruleId: $this->getRuleId(),
            category: FindingCategory::Architecture,
            severity: FindingSeverity::Medium,
            title: "Large number of {$ecosystem} dependencies",
            description: "{$manifestFile} declares {$count} direct dependencies, above the {$threshold}-dependency guideline for {$ecosystem}.",
            filePath: $manifestFile,
            startLine: null,
            endLine: null,
            recommendation: 'Review whether all dependencies are still needed; each one is additional attack surface and maintenance burden.',
            confidence: 0.9,
            analyzer: 'repository',
            metadata: ['count' => $count, 'threshold' => $threshold, 'ecosystem' => $ecosystem],
        );
    }
}
