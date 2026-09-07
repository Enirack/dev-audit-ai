# Security

This is a v1 developer tool audited for the risks realistic at this stage:
a single-tenant-per-account SaaS that ingests public GitHub repositories and
never executes their code. It has not had a professional penetration test.
Treat this document as an honest account of what was checked and why the
current state is acceptable for v1 — not a compliance certification.

## Authentication

- JWT via `lexik/jwt-authentication-bundle`, RS256 keypair generated at
  setup (`config/jwt/*.pem`, gitignored, never committed). Tokens expire
  after 1 hour (the bundle's default `token_ttl`).
- Passwords hashed with Symfony's `auto` password hasher (bcrypt/argon2
  depending on platform support) — never stored or logged in plain text.
- `POST /api/login` (Symfony's `json_login` authenticator) has
  **login throttling**: 5 attempts per 15 minutes per client, configured via
  `security.yaml`'s `login_throttling` (backed by `symfony/rate-limiter`).
  This is the standard Symfony Security mechanism for this, not a custom
  implementation.
- `POST /api/register` is separately rate-limited (5 per hour per IP, see
  `config/packages/rate_limiter.yaml`) against account-creation spam.

## Authorization

Every resource controller (`RepositoryController`, `RepositoryScanController`,
`AuditController`, `AiController`) checks ownership by comparing the
authenticated user's id against the resource's owner before returning
anything — a `Repository` not owned by the current user, or an `Audit`/
`AuditFinding` reached through a `RepositoryScan` whose `Repository` isn't
owned by the current user, returns a plain 404 (never a 403 that would
confirm the resource exists). There is no cross-user data access path.

## Input validation

- `RegisterUserRequest`: email format + password minimum length (Symfony
  Validator constraints).
- `CreateRepositoryRequest`: a custom `GitHubRepositoryUrl` constraint
  backed by `GitHubUrlParser`, which is deliberately strict — see below.
- `FindingFilterCriteria`: category/severity are parsed via
  `FindingCategory::tryFrom()`/`FindingSeverity::tryFrom()` (invalid values
  silently become "no filter" rather than a raw string reaching a query),
  `page`/`perPage` are clamped (`perPage` capped at 100).

## GitHub URL validation (SSRF / path-traversal surface)

`GitHubUrlParser` (`backend/src/Service/GitHub/GitHubUrlParser.php`) is the
single choke point every repository URL passes through before anything is
built from it. It rejects, specifically:

- any scheme other than `https`
- any host other than exactly `github.com` (blocks SSRF via lookalike hosts,
  `github.com.evil.tld`, IP-literal hosts, internal-network hostnames, etc.)
- credentials embedded in the URL (`user:pass@github.com`)
- a non-default port
- `..`, a leading `/`, null bytes, or encoded `%2f`/`%2F` in the path
  (blocks path traversal reaching the codeload URL construction)
- an owner or repo segment that doesn't match GitHub's own allowed
  character set (`OWNER_PATTERN`/`REPO_PATTERN`)

Only a value that survives all of this is ever interpolated into the
codeload.github.com download URL or the GitHub API request path — see
`docs/phase-2-ingestion-architecture.md`.

## Command execution / repository isolation

**No analyzed repository's code is ever executed.** Ingestion downloads a
source-only tarball from `codeload.github.com` (no `.git` directory, no
hooks, no submodules) — there is no `git clone` shell-out anywhere in this
codebase, which removes command-injection-via-clone as a risk class
entirely rather than merely mitigating it. Static analysis
(`backend/src/StaticAnalysis/`) is 100% regex/heuristic pattern matching
over file contents — it never parses, compiles, imports, or evals anything
from the analyzed repository.

Each scan gets an isolated workspace directory (`var/ingestion/<scan-id>`,
mode `0700`), deleted in a `finally` block regardless of success or failure
(`RepositoryScanOrchestrator::run()`).

## Path traversal (archive extraction)

`GitHubIngestionService::extractSafely()` walks every entry in the
downloaded tarball *before* extracting anything, rejecting the whole
archive if any entry's path contains `..`, starts with `/`, contains a null
byte, or is a symbolic link. Uncompressed size is accumulated during that
same walk and checked against `INGESTION_MAX_REPOSITORY_BYTES` before
`PharData::extractTo()` is ever called — this also defends against a
"zip bomb" (a small download that decompresses to an enormous size).

Download size itself is bounded twice: once from the declared
`Content-Length` header (fast-fail), and again by counting actual streamed
bytes (so a server lying about `Content-Length` can't bypass the limit).

## Secret handling

- All secrets (`APP_SECRET`, JWT keypair + passphrase, `GITHUB_TOKEN`,
  `AI_ENGINE_INTERNAL_SECRET`, `ANTHROPIC_API_KEY`) are supplied via
  environment variables, never hardcoded, never committed. The root
  `.gitignore` excludes `.env`; `.env.example` documents what's needed
  without real values.
- The Anthropic API key is read only by the ai-engine process — the
  Symfony backend never sees it.
- Backend → ai-engine requests carry a shared secret (`X-Internal-Auth`),
  checked with `hmac.compare_digest` on the Python side (constant-time
  comparison, not `==`), skipped only when the secret is empty (local/dev).
- Before any text is sent to an LLM provider, `ai-engine`'s
  `redact_secrets()` scans it for AWS keys, GitHub/Slack tokens, PEM
  private-key blocks, bearer tokens, and generic high-entropy
  `key = "..."` assignments, replacing matches with `[REDACTED:<kind>]`.
  See `docs/ai.md`.
- No secret value ever appears in a log statement — checked directly
  (`grep -i "logger->.*\(password\|token\|secret\)"` across `backend/src`
  returns nothing).

## Sensitive logging

Symfony's default `monolog.yaml` (framework skeleton default, unmodified)
logs request-level summaries (method, URI, status), not full request bodies
— a login or register request's password never reaches a log line.
Exception logging (`$this->logger->error(..., ['exception' => $e])`) logs
the exception object, not the request payload that triggered it.

## CORS

`nelmio/cors-bundle`, configured via `CORS_ALLOW_ORIGIN` (a regex),
defaulting to `localhost`/`127.0.0.1` for local development. **Before any
real deployment, `CORS_ALLOW_ORIGIN` must be narrowed to the actual
frontend origin** — this is an environment-variable change, not a code
change, and is called out again in the README's deployment section.

## CSRF

Not applicable by design: every `/api/*` route is `stateless: true` with
JWT bearer-token authentication (`Authorization` header), not cookie-based
sessions. CSRF exploits a browser's automatic cookie attachment to
cross-site requests; there are no cookies here for a forged request to ride
on.

## SQL injection

All database access goes through Doctrine ORM/QueryBuilder with bound
parameters (`->setParameter(...)`) — there is no raw SQL string
concatenation anywhere in `backend/src`.

## XSS

Angular's default template sanitization is untouched: there is no use of
`innerHTML`, `DomSanitizer.bypassSecurityTrust*`, or any other sanitizer
bypass anywhere in `frontend/src`. All dynamic content (finding
descriptions, AI-generated text, repository names) is rendered through
normal Angular interpolation, which HTML-escapes by construction.

## File upload risks

Not applicable — there is no file upload feature. The only way to bring a
repository into the system is a GitHub URL, validated as above.

## API rate limiting

Added in Phase 8 (`symfony/rate-limiter`):

- Login: 5 attempts / 15 minutes per client (`login_throttling`).
- Registration: 5 / hour per client IP.
- Starting an audit: 10 / hour per authenticated user — audits are
  expensive (a GitHub round-trip plus a full ingestion and static-analysis
  pass), so this is as much a cost/availability control as a security one.

GitHub's own API rate limit is respected already (Phase 2):
`GITHUB_TOKEN` is optional — set it to raise the limit from 60/hr to
5000/hr — and a 403/429 from GitHub surfaces as a clean 503 to the caller
rather than a stack trace.

## Known limitations / what a real production deployment must change

- `docker-compose.yml`'s `APP_ENV: dev` is for local development only. A
  real deployment must set `APP_ENV=prod` (disables debug mode and stack
  traces in error responses) and provide a production `APP_SECRET`.
- `CORS_ALLOW_ORIGIN` must be narrowed from the localhost default.
- `AI_ENGINE_INTERNAL_SECRET` must be set to a real shared secret (it's
  empty — and the check is skipped — by default for local development).
- `RepositoryController::list()` and `RepositoryScanController::list()` are
  unpaginated. Fine for v1's expected per-user repository/scan counts; a
  user with hundreds of either would get an unbounded response. Worth
  paginating if this becomes a concern.
- No professional penetration test or third-party security review has been
  performed on this codebase.
