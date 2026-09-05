We are now starting **PHASE 5 — AUDIT SCORING SYSTEM**.

First verify all previous phases.

Do not add AI yet.

## Goal

Transform scanner and static-analysis results into a coherent audit.

The audit must produce:

- overall score
- category scores
- severity distribution
- prioritized findings
- repository statistics
- recommendations

## Categories

Architecture
Security
Maintainability
Performance
Testing
Code Quality

Each category receives a score from 0 to 100.

Overall score should be calculated using documented weights.

Do not choose arbitrary numbers without documenting the reasoning.

## Severity impact

Critical issues should have a substantially larger impact than informational findings.

Avoid allowing hundreds of low-severity findings to completely dominate the score.

## Audit model

Create:

Audit
AuditScore
AuditFinding

An Audit belongs to a RepositoryScan.

The audit should be reproducible from deterministic analysis results.

## Priority

Every finding should have:

- severity
- confidence
- priority

Prioritize issues using a documented algorithm.

## API

Create endpoints to retrieve:

- audit
- scores
- findings
- repository statistics

Support filtering by:

- category
- severity
- file
- rule

Support pagination.

## Report

Create a structured JSON audit report.

The report should be exportable later.

## Tests

Test:

- scoring
- weighting
- severity impact
- empty repository
- perfect repository
- repository with critical findings
- pagination
- filtering

Document the scoring methodology.

Do not add AI yet.

Do not proceed to Phase 6 automatically.