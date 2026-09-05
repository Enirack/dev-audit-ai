<?php

declare(strict_types=1);

namespace App\Audit;

use App\Entity\Enum\FindingCategory;
use App\Entity\Enum\FindingSeverity;
use App\StaticAnalysis\Finding;

/**
 * Pure scoring functions — no I/O, no Doctrine, fully unit-testable with
 * plain arrays of Finding. The formulas and constants here are the single
 * source of truth; docs/scoring.md quotes them verbatim, so keep them in
 * sync if these ever change.
 *
 * Methodology (see docs/scoring.md for the full rationale and worked
 * examples):
 *
 * 1. Each category starts at 100 and loses points per finding in that
 *    category, grouped by severity tier. Each tier's total penalty is
 *    `min(cap, weight * (sum of confidences in that tier) ^ 0.7)` — the
 *    0.7 exponent (<1) gives diminishing returns so the 2nd+ finding in a
 *    tier costs less than the 1st, and the cap prevents any single tier
 *    from zeroing out a category on its own. Category score = 100 - sum of
 *    tier penalties, floored at 0.
 * 2. The overall score is a fixed, documented weighted average of the six
 *    category scores.
 * 3. Priority is severity rank (1-5) times confidence, rounded and clamped
 *    to 1-5 — a small, documented ordinal matrix rather than a black box.
 */
final class ScoringService
{
    /** @var array<string, array{weight: float, cap: float}> */
    public const TIER_PARAMETERS = [
        'critical' => ['weight' => 25.0, 'cap' => 60.0],
        'high' => ['weight' => 12.0, 'cap' => 40.0],
        'medium' => ['weight' => 5.0, 'cap' => 25.0],
        'low' => ['weight' => 1.5, 'cap' => 12.0],
        'info' => ['weight' => 0.3, 'cap' => 5.0],
    ];

    private const DIMINISHING_RETURNS_EXPONENT = 0.7;

    /** @var array<string, float> */
    public const CATEGORY_WEIGHTS = [
        'security' => 0.30,
        'architecture' => 0.20,
        'maintainability' => 0.15,
        'code_quality' => 0.15,
        'testing' => 0.10,
        'performance' => 0.10,
    ];

    /** @var array<string, int> */
    private const SEVERITY_RANK = [
        'critical' => 5,
        'high' => 4,
        'medium' => 3,
        'low' => 2,
        'info' => 1,
    ];

    /**
     * @param Finding[] $findingsInCategory findings already filtered to a single category
     */
    public function computeCategoryScore(array $findingsInCategory): float
    {
        $confidenceSumByTier = array_fill_keys(array_keys(self::TIER_PARAMETERS), 0.0);

        foreach ($findingsInCategory as $finding) {
            $confidenceSumByTier[$finding->severity->value] += $finding->confidence;
        }

        $totalPenalty = 0.0;
        foreach (self::TIER_PARAMETERS as $tier => $params) {
            $confidenceSum = $confidenceSumByTier[$tier];
            if ($confidenceSum <= 0.0) {
                continue;
            }

            $totalPenalty += min($params['cap'], $params['weight'] * ($confidenceSum ** self::DIMINISHING_RETURNS_EXPONENT));
        }

        return round(max(0.0, 100.0 - $totalPenalty), 1);
    }

    /**
     * @param array<string, float> $categoryScores keyed by FindingCategory::value, all six expected
     */
    public function computeOverallScore(array $categoryScores): float
    {
        $overall = 0.0;
        foreach (self::CATEGORY_WEIGHTS as $category => $weight) {
            $overall += ($categoryScores[$category] ?? 100.0) * $weight;
        }

        return round($overall, 1);
    }

    /**
     * @return int priority from 1 (informational, low confidence) to 5 (fix immediately)
     */
    public function computePriority(FindingSeverity $severity, float $confidence): int
    {
        $score = self::SEVERITY_RANK[$severity->value] * $confidence;

        return max(1, min(5, (int) round($score)));
    }

    /**
     * @param Finding[] $findings
     * @return array<string, float> category scores keyed by FindingCategory::value, all six categories present
     */
    public function computeAllCategoryScores(array $findings): array
    {
        $scores = [];
        foreach (FindingCategory::cases() as $category) {
            $inCategory = array_values(array_filter(
                $findings,
                static fn (Finding $f): bool => $f->category === $category,
            ));
            $scores[$category->value] = $this->computeCategoryScore($inCategory);
        }

        return $scores;
    }
}
