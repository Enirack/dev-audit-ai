# Phase 4 — Static analysis engine

## Overview

Deterministic, offline, no LLM involved anywhere in this pipeline:

```
Scanner (Phase 3 inventory + workspace root)
  → language-specific analyzers (thin dispatchers)
  → rules (the only place with actual detection logic)
  → Finding[] (plain value objects, not yet persisted)
```

The AI layer (Phase 6) only ever *consumes* the `Finding[]` this engine
produces; it never replaces or second-guesses the deterministic result.

## Architecture

| Interface / class | Role |
|---|---|
| `Analyzer\AnalyzerInterface` | `supports(extension)`, `analyze(SourceFile): Finding[]` |
| `Analyzer\AbstractRuleBasedAnalyzer` | Thin dispatcher: holds no detection logic, forwards a file to every injected rule that declares support for this analyzer's name |
| `Analyzer\{JavaScript,TypeScript,Php,Python}Analyzer` | Concrete analyzers, one per supported language |
| `Rule\RuleInterface` | Per-file rule: `evaluate(SourceFile, analyzerName): Finding[]` |
| `Rule\RepositoryRuleInterface` | Repository-wide rule: `evaluate(RepositoryAnalysisContext): Finding[]`, runs once per audit instead of once per file |
| `Support\SourceFile` | A file's contents pre-split into lines, plus a best-effort "is this a full-line comment" heuristic used to cut false positives |
| `RepositoryAnalysisContext` | Workspace root + the Phase 3 `RepositoryScanResult`; lets repository-level rules read a specific known file (e.g. `package.json`) directly |
| `StaticAnalysisEngine` | Orchestrates one Finder pass over the workspace, dispatches to the matching analyzer, then runs all repository-level rules, then drops any finding below the confidence floor |

Rules are wired to analyzers via Symfony's tagged-iterator DI
(`_instanceof` + `!tagged_iterator` in `config/services.yaml`) — adding a new
rule only requires implementing the interface and declaring which analyzer
names it supports; no registry class to maintain by hand.

## Why regex/heuristic detection, not a real parser

Per the project's static-analysis constraints (deterministic, modest scope,
no AI), every rule is implemented as a line-based regex or a simple
structural check — no AST is built for any of the four languages, including
PHP (even though `nikic/php-parser` is a mature, obvious choice for PHP
specifically). This keeps all four analyzers structurally symmetric and adds
zero new dependencies. **Trade-off, demonstrated for real** (see "Known
limitations" below): a regex has no notion of "this text is inside a string
literal" beyond the `SourceFile::isFullLineComment()` heuristic, so a rule
can match its own pattern definition when this engine analyzes its own
source code — confirmed by running the engine against `backend/src` itself,
where `DangerousEvalRule.php`'s own pattern strings (e.g. `'/\beval\s*\(/'`)
get flagged by `security.dangerous-eval`. This is a known, accepted
limitation, not a bug — upgrading specific rules to a real parser (starting
with PHP via `nikic/php-parser`) is a localized, interface-compatible change
if false-positive rates prove unacceptable in practice.

## Rule catalog

| Rule ID | Category | Analyzers | Confidence |
|---|---|---|---|
| `security.hardcoded-secret` | Security | all 4 | 0.55 (raw match) or 0.85 (≥24 chars, Shannon entropy ≥ 3.5 bits/char) |
| `security.dangerous-eval` | Security | all 4 | 0.9 |
| `security.unsafe-command-execution` | Security | all 4 | 0.9 if the command string is built via concatenation/interpolation, else 0.4 |
| `quality.long-file` | Code Quality | all 4 (per-language line threshold) | 1.0 |
| `quality.todo-fixme-accumulation` | Code Quality | all 4 | 1.0 (≥5 TODO/FIXME/HACK/XXX markers in one file) |
| `testing.missing-test-directory` | Testing | repository-level | 1.0 |
| `architecture.excessive-dependencies` | Architecture | repository-level (package.json/composer.json/requirements.txt) | 0.9 |

Findings below **0.3 confidence** are dropped by the engine, never reported
(`StaticAnalysisEngine::MIN_CONFIDENCE`).

### `security.hardcoded-secret`

Regex over each non-comment line for an assignment-shaped pattern
(`(api_key|secret|token|password|access_key) [:=] "..."`). Excludes common
placeholder values (`changeme`, `xxx`, `<...>`, `${...}`, `%env(...)%`,
`getenv(...)`-style references never match at all since the RHS must be a
quoted literal). Confidence is boosted to 0.85 only when the literal is long
*and* high-entropy, which is what actually distinguishes a real secret from
a short/low-entropy string that merely matches the shape.

### `security.dangerous-eval`

`eval`/`new Function` (JS/TS), `eval` (PHP), `eval`/`exec` (Python). High,
fixed confidence — this pattern is rarely a false positive as a *pattern*
(see "Known limitations" for the one real exception found in dogfooding).

### `security.unsafe-command-execution`

`exec`/`execSync` (JS/TS, not `execFile`/`spawn` which take argument
arrays), `exec`/`shell_exec`/`system`/`passthru`/`proc_open` (PHP),
`os.system`/`subprocess.{call,run,Popen}(..., shell=True)` (Python).
Confidence jumps from 0.4 to 0.9 when the same line shows a concatenation/
interpolation hint (`${`, `" .`, `.concat(`, an f-string, `.format(`, `%
(`) — that is the actually dangerous shape, not command execution per se.

### `quality.long-file`

Non-blank line count above a per-language threshold (documented constants:
500 for JS/TS/PHP, 600 for Python — Python idiomatically runs a bit longer
per file). Purely a count, so confidence is always 1.0.

### `quality.todo-fixme-accumulation`

Counts `TODO|FIXME|HACK|XXX` occurrences per file; flags at ≥5. A literal
string match, so confidence is 1.0 — the "is this actually a problem"
judgment is left to the threshold, not to detection uncertainty.

### `testing.missing-test-directory`

Repository-level: flags when the Phase 3 inventory found at least one source
file in a supported language but zero of the conventional test directory
names (`tests/`, `test/`, `__tests__/`, `spec/`).

### `architecture.excessive-dependencies`

Repository-level: parses `package.json` (`dependencies` + `devDependencies`
count > 80), `composer.json` (`require` + `require-dev` > 40), and
`requirements.txt` (non-comment, non-blank line count > 50) — reads only
these three specific, already-known-to-exist files directly off the
workspace, not arbitrary repository content.

## Known limitations

- **No AST for any language.** Regex/heuristic detection can match inside
  what a real parser would recognize as a string or comment. The
  `SourceFile::isFullLineComment()` check covers the common "whole line is
  commented out" case but not, e.g., a match that happens to sit inside a
  regex-pattern string literal — demonstrated concretely by running the
  engine against its own source tree (see above).
- `security.unsafe-command-execution`'s interpolation detection is a fixed
  list of textual hints, not real expression parsing; it can miss less
  common interpolation styles.
- `architecture.excessive-dependencies` counts only *direct* declared
  dependencies (not resolved/transitive ones), since resolving those would
  require running a package manager — explicitly out of scope
  ("never execute code from analyzed repositories").
- Cross-file duplication, circular-dependency detection, and per-function
  complexity/nesting rules from the wider rule catalog considered during
  design are not implemented in this phase — the initial set intentionally
  stays inside "useful but manageable," per the phase's own instruction, and
  can be extended later using the same `RuleInterface`/`RepositoryRuleInterface`
  contracts without touching the engine.

## Tests

53 unit tests under `tests/Unit/StaticAnalysis/`: one positive/negative
(and, where relevant, edge-case) test per rule, plus an end-to-end
`StaticAnalysisEngineTest` proving ignored directories are never analyzed
and both per-file and repository-level findings are produced together.

## Extending the engine

1. Implement `RuleInterface` (per-file) or `RepositoryRuleInterface`
   (repository-wide) under `src/StaticAnalysis/Rule/<Category>/`.
2. It's auto-tagged and auto-wired to every analyzer that lists its name in
   `supportedAnalyzers()` — no manual registration needed.
3. Add a unit test with at least one positive and one negative case.
