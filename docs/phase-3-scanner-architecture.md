# Phase 3 — Repository scanner architecture

## Goal

Turn an ingested repository workspace (Phase 2's output) into a structured,
deterministic inventory: files, directories, languages, line counts,
metadata files — without executing anything and without ever reading the
contents of a real secret-carrying file.

## Pipeline

```
RepositoryScanOrchestrator (after ingestion, status: scanning)
  → RepositoryScanner::scan($workspace->root)
      → Symfony Finder walk, excluding configured directories
      → per file: language detection, line counting, binary detection,
        metadata detection
      → RepositoryScanResult (plain value object)
  → RepositoryInventory entity (persisted, 1:1 with RepositoryScan)
```

## Components

| Class | Responsibility |
|---|---|
| `ScannerConfig` | Value object for all limits/ignore lists, sourced from `config/packages/scanner.yaml` |
| `RepositoryWalker` (via `Symfony\Component\Finder\Finder`) | Directory traversal, excluding ignored directories; symlinks are never followed (Finder's default) |
| `LanguageDetector` | Extension → language map (JS/TS/PHP/Python/HTML/CSS/JSON/YAML/Markdown) |
| `LineCounter` | Streams a file in 8KB chunks to count lines without loading it into memory |
| `BinaryFileDetector` | Known binary extensions, falling back to a null-byte sniff of the first 8KB |
| `SensitiveFileGuard` | Identifies real `.env*` files (excluding `.example`/`.sample`/`.dist`/`.test` templates) whose contents must never be read |
| `RepositoryMetadataDetector` | Detects well-known metadata files/directories by name only — never opens file contents |
| `RepositoryScanner` | Orchestrates a single Finder pass, applying all of the above and enforcing `maxFiles`/`maxRepositorySizeBytes` |

## Ignore rules (configurable, `config/packages/scanner.yaml`)

- Directories excluded from traversal entirely: `.git`, `node_modules`,
  `vendor`, `dist`, `build`, `out`, `coverage`, `.next`, `.nuxt`, `.cache`,
  `__pycache__`, `.venv`, `venv`, `.idea`, `.vscode`, `.pytest_cache`,
  `.mypy_cache`.
- File name patterns excluded from language/line analysis (but still counted
  in `ignoredFileCount`): `*.min.js`, `*.min.css`, `*.map`, `*.log`.
- Binary extensions are skipped from line counting outright; anything else
  gets a null-byte sniff of its first 8KB as a fallback.

## The secret-safety invariant

`RepositoryMetadataDetector` only ever receives a relative path and a
boolean — it has no code path that opens a file. `RepositoryScanner` checks
`SensitiveFileGuard::isSensitiveEnvFile()` **before** calling the line
counter or binary detector on any file, so a real `.env` file's contents are
never read at all; only its existence is recorded
(`metadata.envFilePresent = true`). This is enforced structurally, not just
by convention, and is covered by
`RepositoryScannerTest::testSensitiveEnvFileContentIsNeverExposed`, which
asserts a fake secret value placed in the fixture `.env` file never appears
anywhere in the scan result.

## Performance

- Single `Finder` pass (no separate file/directory scans).
- Lines are counted via chunked streaming (`LineCounter`), never
  `file_get_contents()`.
- Files over `scanner.max_file_size_bytes` (default 5MB) are skipped for line
  counting.
- The scan stops early (`metadata.truncated = true`) if `scanner.max_files`
  or `scanner.max_repository_size_bytes` is exceeded — defense in depth on
  top of the ingestion-time size cap from Phase 2.

## Data model

`RepositoryInventory` (new entity, one-to-one with `RepositoryScan`, same
convention as `Audit`): file/directory/size/binary/ignored counts,
`languageStats` (JSON: `{"TypeScript": {"files": 1, "lines": 6}, ...}`),
`extensionStats` (JSON), `metadata` (JSON: detected files, test directories,
`truncated` flag). Kept as a separate entity/table (not a JSON column bolted
onto `RepositoryScan`) so a cheap scan-status poll never has to deserialize a
potentially large blob.

## Tests

34 unit tests (`tests/Unit/Service/Scanning/`), including an end-to-end
`RepositoryScannerTest` against a fixture repository
(`tests/Fixtures/scanner/sample-repo/`) covering: language/line stats,
ignored directories excluded entirely, ignored file patterns counted but not
analyzed, binary file detection, metadata file detection, the secret-safety
invariant, the `maxFiles` truncation path, and symlink handling (skipped
gracefully on platforms where creating a symlink isn't permitted, e.g.
Windows without Developer Mode).

## Known limitations

- Language detection is extension-based only, not content-based; a
  misnamed file will be misclassified.
- `ignoredFileCount` counts files matching ignore *patterns* found by the
  walker; files inside excluded *directories* are never seen at all and are
  not part of that count.
