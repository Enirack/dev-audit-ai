We are now starting **PHASE 4 — STATIC ANALYSIS ENGINE**.

The analysis must be deterministic wherever possible.

Do not use an LLM for basic detection.

## Goal

Build a modular static analysis engine for:

- JavaScript
- TypeScript
- PHP
- Python

Architecture:

Scanner
→ language-specific analyzers
→ rules
→ findings

## Analyzer architecture

Create a common interface for analyzers.

Conceptually:

Analyzer
├── JavaScriptAnalyzer
├── TypeScriptAnalyzer
├── PHPAnalyzer
└── PythonAnalyzer

Rules must be modular.

Conceptually:

Rule
├── Security rules
├── Quality rules
├── Architecture rules
├── Performance rules
└── Testing rules

## Initial rules

Implement a useful but manageable set.

Security:

- hardcoded secrets where confidently detectable
- dangerous eval usage
- unsafe command execution patterns
- suspicious SQL construction
- insecure configuration patterns

Quality:

- extremely long files
- extremely long functions where detectable
- excessive nesting
- TODO/FIXME accumulation
- duplicated obvious patterns where feasible

Architecture:

- excessive dependencies
- circular dependency detection where feasible
- suspicious layer violations where detectable

Testing:

- missing test directories
- source files with no apparent tests
- low test presence indicators

Performance:

- obvious expensive patterns
- synchronous filesystem operations in inappropriate contexts where detectable
- suspicious loops where safely identifiable

## Finding structure

Every finding should contain:

- ruleId
- category
- severity
- title
- description
- filePath
- startLine
- endLine
- recommendation
- confidence
- analyzer
- metadata

Severity:

info
low
medium
high
critical

## Important

Do not produce fake findings.

If a rule cannot confidently detect a problem, lower its confidence or do not report it.

Avoid simplistic rules that generate huge numbers of false positives.

## AI separation

The static analyzer must work completely without an LLM.

The AI layer will consume structured findings later.

## Tests

Every rule must have:

- positive test
- negative test
- edge cases where relevant

Create fixtures under a dedicated test fixtures directory.

Document every rule.

Do not proceed to Phase 5 automatically.