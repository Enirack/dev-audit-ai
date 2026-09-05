<?php

declare(strict_types=1);

namespace App\StaticAnalysis;

use App\Service\Scanning\ScannerConfig;
use App\StaticAnalysis\Analyzer\AnalyzerInterface;
use App\StaticAnalysis\Rule\RepositoryRuleInterface;
use App\StaticAnalysis\Support\SourceFile;
use Symfony\Component\Finder\Finder;

/**
 * Scanner -> language-specific analyzers -> rules -> findings.
 *
 * Deterministic and fully offline: no LLM call happens anywhere in this
 * namespace. The AI layer (a later phase) only ever consumes the Finding[]
 * this engine produces; it never replaces it.
 */
final class StaticAnalysisEngine
{
    /** Findings below this confidence are dropped rather than reported. */
    private const MIN_CONFIDENCE = 0.3;

    private const GENERATED_FILE_PATTERNS = ['*.min.js', '*.min.css', '*-lock.*', '*.generated.*'];

    /**
     * @param iterable<AnalyzerInterface>       $analyzers
     * @param iterable<RepositoryRuleInterface> $repositoryRules
     */
    public function __construct(
        private readonly iterable $analyzers,
        private readonly iterable $repositoryRules,
        private readonly ScannerConfig $scannerConfig,
    ) {
    }

    /** @return Finding[] */
    public function analyze(RepositoryAnalysisContext $context): array
    {
        $findings = [];

        $finder = Finder::create()
            ->files()
            ->in($context->workspaceRoot)
            ->ignoreDotFiles(false)
            ->ignoreVCS(true)
            ->exclude($this->scannerConfig->ignoredDirectories);

        foreach ($finder as $fileInfo) {
            $filename = $fileInfo->getFilename();
            if ($this->isGeneratedFile($filename)) {
                continue;
            }

            $extension = $fileInfo->getExtension();
            $analyzer = $this->findAnalyzer($extension);
            if (null === $analyzer) {
                continue;
            }

            if ($fileInfo->getSize() > $this->scannerConfig->maxFileSizeBytes) {
                continue;
            }

            $contents = file_get_contents($fileInfo->getPathname());
            if (false === $contents) {
                continue;
            }

            $relativePath = str_replace('\\', '/', $fileInfo->getRelativePathname());
            $sourceFile = new SourceFile($relativePath, $contents);

            array_push($findings, ...$analyzer->analyze($sourceFile));
        }

        foreach ($this->repositoryRules as $rule) {
            array_push($findings, ...$rule->evaluate($context));
        }

        return array_values(array_filter(
            $findings,
            static fn (Finding $finding): bool => $finding->confidence >= self::MIN_CONFIDENCE,
        ));
    }

    private function findAnalyzer(string $extension): ?AnalyzerInterface
    {
        foreach ($this->analyzers as $analyzer) {
            if ($analyzer->supports($extension)) {
                return $analyzer;
            }
        }

        return null;
    }

    private function isGeneratedFile(string $filename): bool
    {
        foreach (self::GENERATED_FILE_PATTERNS as $pattern) {
            if (fnmatch($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }
}
