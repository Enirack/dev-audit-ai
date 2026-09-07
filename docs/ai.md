# Phase 6 — AI Intelligence Layer

## Goal

The AI layer enriches the deterministic Phase 3-5 pipeline (scan → static
analysis → scoring). It never replaces it, never invents findings, and never
modifies a repository. It adds five features on top of already-persisted
data: finding explanations, an executive summary, an architecture
explanation, a prioritized refactoring plan, and a repository Q&A chat.

```
Repository (already scanned, audited, scored)
  -> Symfony (AiPayloadBuilder): assembles deterministic JSON from
     Audit / AuditFinding / RepositoryInventory
  -> ai-engine (Python/FastAPI): ContextBuilder budgets + redacts it,
     AIProvider generates structured output, HallucinationGuard filters it
  -> Symfony (AiInsightService): caches the result, returns it to the API
```

## Important constraint: no source code at insight time

The ingested repository workspace is deleted as soon as a scan finishes
(`GitHubIngestionService::cleanup()`, called in `RepositoryScanOrchestrator`'s
`finally` block — see `docs/phase-2-ingestion-architecture.md`). AI insights
are typically requested well after that, from the dashboard. **This means no
raw source-code snippet is ever available to the AI layer** — only the
structured `Finding` records (title, description, file path, line range,
rule id, confidence, severity, category, recommendation) and the
`RepositoryInventory` aggregate stats (language/extension breakdowns, file
counts, size, boolean metadata flags) that Phases 3-4 persisted to the
database.

This is a deliberate, documented trade-off rather than an oversight: re-
fetching/re-cloning a repository on every AI request would add real cost and
new attack surface (a second ingestion path) for a feature that isn't
required for v1. The practical effect: the AI explains and reasons about
findings and repository metadata, not literal code. Every prompt sent to the
provider says so explicitly, and the architecture-explanation feature is
told outright that no source code is available.

## Provider abstraction

`ai-engine/app/ai/provider.py` defines a small `AIProvider` Protocol:

```python
def generate_structured(self, *, system_prompt, user_prompt, json_schema, schema_name, max_tokens) -> dict: ...
```

Two implementations exist:

- `MockProvider` (`ai-engine/app/ai/mock_provider.py`) — the default
  (`AI_ENGINE_PROVIDER=mock`), returns deterministic canned responses, never
  makes a network call. Used in all automated tests and in any environment
  without an API key configured.
- `AnthropicProvider` (`ai-engine/app/ai/anthropic_provider.py`) —
  `AI_ENGINE_PROVIDER=anthropic`, requires `AI_ENGINE_ANTHROPIC_API_KEY`.
  Forces structured output via Anthropic tool use: the JSON schema of the
  expected content *is* the tool's `input_schema`, and `tool_choice` forces
  the model to call it. The SDK parses the JSON, so there is no regex/
  free-text parsing of a completion anywhere in this codebase.

`ai-engine/app/ai/factory.py` selects between them from `Settings`. Adding a
third provider means implementing the Protocol and adding one branch to the
factory — nothing else in the codebase (routers, context builder, schemas,
hallucination guard) depends on which provider is active.

## Context building and token/character budgeting

`ai-engine/app/context/builder.py`'s `ContextBuilder` is the only place that
turns a request payload into the actual prompt sent to a provider:

1. **Redaction** (`ai-engine/app/redaction/secrets.py`) — every free-text
   field (finding title/description/recommendation, chat question, chat
   history) is scanned for AWS keys, GitHub/Slack tokens, PEM private-key
   blocks, bearer tokens, and generic high-entropy `key = "..."` assignments,
   and matches are replaced with `[REDACTED:<kind>]` before anything is
   built into a prompt. This is a last line of defense against a secret that
   leaked into persisted finding text or that a user pastes into chat — it
   is not a repository scanner (see the constraint above: there is no
   repository to scan at this point).
2. **Budgeting** — findings are sorted by `(priority, confidence)` descending
   and packed into the prompt until `Settings.max_context_characters`
   (default 24,000, character-based rather than a real tokenizer — a
   conservative ~4 chars/token approximation, chosen to avoid an extra
   tokenizer dependency) is reached. Anything that doesn't fit is dropped,
   and the response's `context_truncated` flag is set so callers know the
   AI didn't see the full finding set.
3. **Known-reference tracking** — the builder records exactly which file
   paths and finding ids/rule ids were actually included in the prompt. This
   set is the ground truth the hallucination guard checks the model's output
   against.

## Hallucination guard

The system prompt (`BASE_SYSTEM_PROMPT` in `context/builder.py`) instructs
the model to reference only files/findings from the "known" lists it was
given, and to say so explicitly when context is insufficient rather than
guess. `ai-engine/app/validation/hallucination_guard.py` then mechanically
verifies this: for any field where the model is asked to cite file paths or
finding ids (refactoring plan priorities, chat answer references), each
citation is checked against the known set built in step 3 above. An invented
reference is **dropped, not treated as a fatal error** — the response still
returns, with a warning appended (`"AI referenced unknown file 'x' — removed
(not present in provided context)."`). This matches the "explicitly say when
context is insufficient" rule rather than crashing a whole feature over one
bad citation.

## Distinguishing deterministic data, AI interpretation, and AI recommendation

Every AI endpoint response separates:

- **deterministic data** — supplied by Symfony in the request (findings,
  scores, repository metadata) and never altered by the AI layer.
- **`ai_interpretation`** — the model's explanation/analysis (e.g. a
  finding's problem/why-it-matters/impact, an executive summary's
  strengths/weaknesses/risks, an architecture overview).
- **`ai_recommendation`** — the model's suggested action (a finding's
  remediation, an executive summary's next steps, the refactoring plan
  itself).

See `ai-engine/app/schemas/responses.py` for the exact per-endpoint shape.

## Endpoints

All under `POST /ai/*` on the ai-engine (internal only, see Security below),
proxied by Symfony under `/api/audits/{auditId}/ai/*`:

| Symfony route | ai-engine route | Purpose |
|---|---|---|
| `POST /api/audits/{id}/ai/findings/{findingId}/explain` | `POST /ai/findings/explain` | Explain one finding |
| `POST /api/audits/{id}/ai/summary` | `POST /ai/summary` | Executive summary |
| `POST /api/audits/{id}/ai/architecture` | `POST /ai/architecture` | Architecture explanation |
| `POST /api/audits/{id}/ai/refactoring-plan` | `POST /ai/refactoring-plan` | Prioritized refactoring plan |
| `POST /api/audits/{id}/ai/chat` | `POST /ai/chat` | Ask a question about the repository |
| `GET /api/audits/{id}/ai/chat` | — | Read the persisted conversation |

Symfony-side endpoints accept `?refresh=true` to bypass the cache and force
regeneration (see Caching below); it is otherwise ignored.

## Caching

`AiInsight` caches one row per `(audit, type[, finding])` — an audit is
immutable once created (no re-scan mutates it), so a cached insight never
goes stale on its own. Finding explanations, the executive summary, the
architecture explanation, and the refactoring plan are all cached this way;
the ai-engine is called again only when no cached row exists or `?refresh=
true` is passed. Chat is never cached — each question gets a fresh answer —
but the conversation itself is persisted (`AiChatMessage`) so the model
receives prior turns as context and the frontend can show history.

## Security

- **Internal auth**: the ai-engine's `/ai/*` routes require an
  `X-Internal-Auth` header matching `AI_ENGINE_INTERNAL_SECRET`
  (`ai-engine/app/security/internal_auth.py`, `AiEngineClient::post()`).
  When that env var is empty (the default in local/dev), the check is
  skipped on both sides rather than locking out a fresh checkout — set the
  same non-empty value on both services in any shared/production
  environment.
- **Secret redaction** happens before any text reaches a provider (see
  above) — this holds regardless of which provider is configured.
- **No autonomous action**: nothing in this layer modifies a repository,
  opens a pull request, or writes back to GitHub. It only reads
  already-persisted audit data and returns text.
- The Anthropic API key itself is read only by the ai-engine process
  (`AI_ENGINE_ANTHROPIC_API_KEY`); Symfony never sees it.

## Testing

`ai-engine/app/tests/` (37 tests, all using `MockProvider` — no network
calls, no API key required):

- `test_mock_provider.py` — success/timeout/failure/malformed modes.
- `test_anthropic_provider.py` — tool-use success, missing tool-use block,
  non-dict tool input, timeout and connection-error mapping (using real
  `anthropic.APITimeoutError`/`APIConnectionError` instances against a
  mocked `messages.create`).
- `test_redaction.py` — each secret pattern, and that placeholders
  (`changeme`, `xxx...`) are left alone.
- `test_context_builder.py` — budgeting/truncation, known-file/finding
  tracking, redaction propagation, chat history capping.
- `test_hallucination_guard.py` — known references kept, unknown ones
  dropped with a warning.
- `test_ai_endpoints.py` — full FastAPI request/response cycle per endpoint
  (success, timeout → 504, provider failure → 502, malformed provider
  output → 502, hallucinated references filtered out of the response,
  internal-auth accept/reject).

## Known limitations (v1)

- No source-code snippets (see the constraint above) — this is the single
  biggest limitation and is intentional for this phase.
- Character-based context budgeting is an approximation, not a real
  tokenizer count.
- The hallucination guard only checks explicit citation fields
  (`referenced_files`/`referenced_findings`); it does not scan free-form
  prose for invented file names.
- Caching is per-row lookup-then-insert with no unique DB constraint —
  a genuine concurrent double-request race could in theory create two
  cached rows for the same key. Not a correctness issue for a single user
  clicking a button twice in practice; would be worth a unique index if this
  becomes a shared/high-concurrency deployment.
