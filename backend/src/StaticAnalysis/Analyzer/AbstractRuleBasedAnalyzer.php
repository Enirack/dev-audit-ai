<?php

declare(strict_types=1);

namespace App\StaticAnalysis\Analyzer;

use App\StaticAnalysis\Finding;
use App\StaticAnalysis\Rule\RuleInterface;
use App\StaticAnalysis\Support\SourceFile;

/**
 * A thin dispatcher: holds no detection logic itself, only forwards a file
 * to every rule that declares support for this analyzer's name.
 */
abstract class AbstractRuleBasedAnalyzer implements AnalyzerInterface
{
    /** @var RuleInterface[] */
    private array $rules;

    /** @param iterable<RuleInterface> $rules */
    public function __construct(iterable $rules)
    {
        $this->rules = array_values(array_filter(
            $rules instanceof \Traversable ? iterator_to_array($rules) : $rules,
            fn (RuleInterface $rule): bool => in_array($this->getName(), $rule->supportedAnalyzers(), true),
        ));
    }

    /** @return Finding[] */
    public function analyze(SourceFile $file): array
    {
        $findings = [];
        foreach ($this->rules as $rule) {
            array_push($findings, ...$rule->evaluate($file, $this->getName()));
        }

        return $findings;
    }
}
