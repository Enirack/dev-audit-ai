# Phase 2 — GitHub ingestion architecture

## Goal

Accept a public GitHub repository URL, validate it strictly, and retrieve its
source code into an isolated, size- and time-bounded temporary workspace —
without ever executing anything from the repository.

## Flow

```
POST /api/repositories { url }
  → GitHubUrlParser.parse()        (validation, no I/O)
  → duplicate check (owner, url)   (Doctrine)
  → GitHubApiClient.getRepositoryInfo()   (existence + default branch)
  → persist Repository

POST /api/repositories/{id}/scans
  → create RepositoryScan (status: pending)
  → RepositoryScanOrchestrator.run()
      → markCloning()
      → GitHubIngestionService.ingest()   (download + safe extraction)
      → GitHubApiClient.resolveCommitSha()
      → markScanning()                    (Phase 3 will do real scanning here)
      → markCompleted() / markFailed()
      → cleanup workspace (always, via try/finally)
```

## Why a tarball download instead of `git clone`

`GitHubIngestionService` downloads a tarball snapshot from GitHub's `codeload`
service over plain HTTPS (`symfony/http-client`) rather than shelling out to
the `git` binary. This removes an entire class of risk instead of merely
mitigating it:

- No `.git` directory, no hooks, no submodules, no credential helpers.
- No command-line argument construction at all — nothing to inject into.
- The archive is a content snapshot; extracting it can never trigger a
  network operation or execute repository code.

## Security controls

| Concern | Control |
|---|---|
| Non-GitHub / malformed URLs | `GitHubUrlParser`: exact `github.com` host match (not a substring/regex), `https` scheme only, no credentials, no port, path must resolve to exactly two safe segments (`owner/repo`) |
| Path traversal | Decoded path checked for `..`, NUL bytes, backslashes, encoded slashes, before it ever reaches a validator or the ingestion service |
| Command injection | No shell/process invocation anywhere in the ingestion path — only HTTP requests with a URL built from already-validated owner/repo segments |
| Oversized downloads | `Content-Length` checked before streaming; a running byte counter aborts the download mid-stream if it exceeds `INGESTION_MAX_ARCHIVE_BYTES` |
| Oversized repositories (zip-bomb-style) | Every tar entry's declared size is summed before extraction; aborts if the total exceeds `INGESTION_MAX_REPOSITORY_BYTES` |
| Timeouts | `max_duration` on the HTTP request; `IngestionTimeoutException` on timeout |
| Unsafe archive entries | Every entry's path is checked for `..`/absolute paths/NUL bytes and rejected if it is a symlink, **before** any extraction happens |
| Never executing repository code | Nothing in the ingestion path parses, requires, includes, or shells out to any file inside the extracted workspace |
| Never trusting repository filenames | Filenames are only ever used as relative paths under a workspace directory that is deleted afterward; no filename is passed to a shell or `eval` |
| Temporary file cleanup | `RepositoryScanOrchestrator` always calls `GitHubIngestionService::cleanup()` in a `finally` block, and `GitHubIngestionService::ingest()` itself removes the workspace on any failure during download/extraction |

## Status lifecycle

`RepositoryScan::status` (`ScanStatus` enum): `pending → cloning → scanning → completed | failed`.

As of Phase 2, `scanning` is set but not yet followed by an actual scan —
that is Phase 3's responsibility. `errorMessage` is always one of a small set
of fixed, human-safe strings (never a raw exception message, stack trace, or
filesystem path); the real exception is sent to the `LoggerInterface`
(Monolog) instead.

## Synchronous execution (V1)

No message queue (`symfony/messenger`) is installed. `POST /scans` runs
ingestion synchronously and returns the **final** status (`completed` or
`failed`) in the `201` response rather than an async-implying `202`. This is
an intentional V1 scope decision — acceptable for small public repositories,
revisit if repository sizes/latency become a problem.

## Duplicate detection

`Repository` has a unique constraint on `(owner_id, url)`. The import service
also checks explicitly before insert so a duplicate returns a clean `409`
instead of a raw database constraint violation.

## Environment variables

| Variable | Default | Purpose |
|---|---|---|
| `GITHUB_TOKEN` | empty | Optional; raises the GitHub API rate limit from 60/hr to 5000/hr |
| `INGESTION_MAX_ARCHIVE_BYTES` | 104857600 (100MB) | Max compressed tarball download size |
| `INGESTION_MAX_REPOSITORY_BYTES` | 209715200 (200MB) | Max uncompressed repository size |
| `INGESTION_TIMEOUT_SECONDS` | 60 | Max time allowed for the download |

## Verified

Manually verified end-to-end against `docker compose up`, using
`octocat/Hello-World` as a real public repository:

- `POST /api/repositories` with a valid URL → `201`, correct default branch resolved (`master`).
- Same URL again → `409` (duplicate).
- Non-GitHub host, path traversal, credentials-in-URL → `422` (rejected by the validator, never reach the ingestion service).
- Nonexistent repository → `422` with a safe error message.
- `POST /api/repositories/{id}/scans` → `201`, `status: completed`, real commit sha resolved.
- Workspace directory removed after the scan (`var/ingestion` empty in the container).
- Unauthenticated request → `401`.

## Known limitations / not yet implemented

- No automated integration test hits the real network yet (manual
  verification only, documented above) — a `RepositoryIngestionIntegrationTest`
  that can be skipped gracefully when offline is planned.
- Circular-dependency/architecture rules, scanning itself, and everything
  from Phase 3 onward are not implemented yet.
