# Phase 5 — Audit scoring methodology

This document is the human-readable mirror of `App\Audit\ScoringService`,
which is the actual source of truth (its class constants are quoted
verbatim below). If the two ever disagree, the code is right and this file
is stale — but they should never disagree.

## Model

```
Audit                  (1:1 with RepositoryScan; created right after the
                         Phase 3 scan + Phase 4 static analysis complete,
                         while the ingested workspace still exists)
  ├── AuditFinding[]    (persisted Finding[] output from Phase 4, each with
  │                      a computed priority)
  └── AuditScore[]      (one per FindingCategory, always all six)
  overallScore          (a single float on Audit itself, computed once)
```

The audit is fully reproducible: given the same deterministic findings, the
same score always comes out — no randomness, no AI involved anywhere in
this pipeline.

## Category scores (0-100)

Each category starts at 100 and loses points per finding in that category,
**grouped by severity tier first**, not per finding individually — this is
what makes the "critical dominates, low-severity noise doesn't" requirement
work.

For each severity tier, the tier's total penalty is:

```
penalty(tier) = min(cap[tier], weight[tier] * (Σ confidence)^0.7)
```

where `Σ confidence` is the sum of the `confidence` value of every finding
in that tier (so a 0.4-confidence finding contributes less than a
1.0-confidence one), and the `^0.7` exponent (less than 1) gives
**diminishing returns**: the 2nd finding in a tier costs less marginal
penalty than the 1st, the 10th costs very little more than the 9th. The
`cap` is a hard ceiling so no single tier can, on its own, zero out a
category.

```php
public const TIER_PARAMETERS = [
    'critical' => ['weight' => 25.0, 'cap' => 60.0],
    'high'     => ['weight' => 12.0, 'cap' => 40.0],
    'medium'   => ['weight' => 5.0,  'cap' => 25.0],
    'low'      => ['weight' => 1.5,  'cap' => 12.0],
    'info'     => ['weight' => 0.3,  'cap' => 5.0],
];
```

`categoryScore = round(max(0, 100 - Σ penalty(tier) over all tiers), 1)`

**Worked example** — Security category with 1 critical (confidence 0.9), 3
high (confidences 0.8, 0.75, 0.6), 10 low (≈0.6 each):

- critical: `min(60, 25 * 0.9^0.7) = min(60, 23.2) = 23.2`
- high: sum = 2.15 → `min(40, 12 * 2.15^0.7) = min(40, 21.0) = 21.0`
- low: sum = 6.0 → `min(12, 1.5 * 6.0^0.7) = min(12, 5.07) = 5.07`
- total penalty = 49.27 → **Security score = 50.7**

**Contrast** — 100 low-severity findings instead (sum of confidences ≈ 60):
`min(12, 1.5 * 60^0.7) = min(12, 26.1) = 12` (hits the cap) → **score = 88**.
A hundred low-severity findings can never push a category below 88; one
critical finding alone already costs 23 points. This is the concrete proof
that severity, not finding *count*, drives the score.

**Empty category (no findings at all) → score = 100** (perfect), since the
penalty sum is 0.

## Overall score

A fixed, documented weighted average of the six category scores:

```php
public const CATEGORY_WEIGHTS = [
    'security'        => 0.30,
    'architecture'    => 0.20,
    'maintainability' => 0.15,
    'code_quality'    => 0.15,
    'testing'         => 0.10,
    'performance'     => 0.10,
];
```

**Rationale for these specific weights** (so they don't look arbitrary):
Security is weighted highest because security defects carry disproportionate
real-world risk. Architecture is next because structural problems compound
over a codebase's lifetime. Maintainability and Code Quality are weighted
equally — both reflect long-term developer cost. Testing and Performance are
weighted lowest not because they don't matter, but because this V1
deterministic engine has the *weakest measurement signal* for them
(presence-based heuristics, not real coverage or profiling) — weights are
lower where confidence in the underlying measurement itself is lower.

**Worked example**: category scores 50.7 / 90.0 / 85.0 / 78.0 / 40.0 / 95.0
→ `50.7×0.30 + 90×0.20 + 85×0.15 + 78×0.15 + 40×0.10 + 95×0.10 = 71.16` →
**overall = 71.2**.

## Priority

Every finding gets an integer priority from 1 (informational, low
confidence) to 5 (fix immediately), computed — not guessed — from severity
and confidence:

```php
$severityRank = ['critical' => 5, 'high' => 4, 'medium' => 3, 'low' => 2, 'info' => 1];
$priority = max(1, min(5, round($severityRank[$severity] * $confidence)));
```

This is a small, fully-documented ordinal matrix rather than a black box.
The findings API defaults to sorting by `priority DESC, confidence DESC`.

## API

`GET /api/audits/{id}` — overall score, summary, severity distribution,
finding count.
`GET /api/audits/{id}/scores` — all six category scores.
`GET /api/audits/{id}/findings` — paginated (`page`, `perPage`, max 100),
filterable by `category`, `severity`, `file` (substring), `rule`.
`GET /api/audits/{id}/statistics` — repository stats from the Phase 3
inventory plus findings-by-category/severity breakdowns.
`GET /api/audits/{id}/report` — the full structured JSON report (all of the
above combined), for future export.

## Tests

`tests/Unit/Audit/ScoringServiceTest.php`: empty category → 100, a single
critical finding's exact penalty (regression-pinned to the worked example
above), 100 low-severity findings capped ≥ 88, confidence scaling the
penalty, a critical finding scoring worse than 100 low-severity findings
combined, the overall-score weighted-sum worked example, all-100 categories
→ overall 100, and a full priority matrix across severity × confidence.
