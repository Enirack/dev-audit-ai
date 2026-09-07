# Architecture

DevAudit AI is a monorepo made of four independently deployable services, orchestrated locally with Docker Compose.

```
┌────────────┐      HTTP (JSON)      ┌────────────┐      HTTP (JSON)      ┌────────────┐
│  frontend  │  ───────────────────▶ │  backend   │  ───────────────────▶ │ ai-engine  │
│  Angular   │ ◀───────────────────  │  Symfony 8 │ ◀───────────────────  │  FastAPI   │
└────────────┘                       └─────┬──────┘                      └────────────┘
                                            │ Doctrine ORM
                                            ▼
                                     ┌────────────┐
                                     │ PostgreSQL │
                                     └────────────┘
```

## Services

### backend (Symfony 8 / PHP 8.4)

The system of record. Owns authentication, the domain model (users, repositories, scans,
audits, findings, scores) and the public REST API consumed by the frontend. Talks to
`ai-engine` over HTTP for anything that needs an LLM.

- Doctrine ORM + migrations against PostgreSQL.
- JWT authentication via LexikJWTAuthenticationBundle.
- `GET /api/health` reports its own status plus the reachability of PostgreSQL and ai-engine.

### frontend (Angular 22)

The dashboard. A standalone-components Angular app (esbuild application builder) that
consumes the backend's REST API. No business logic lives here beyond presentation and
client-side validation.

### ai-engine (Python / FastAPI)

The analysis/AI engine. Reached by the backend over the internal Docker network
(`http://ai-engine:8001`). Kept as a separate service because Python has the strongest
ecosystem for static-analysis tooling and LLM SDKs; isolating it also means the backend
never needs to trust or execute anything from an analyzed repository directly.

### infrastructure (Docker Compose)

`docker-compose.yml` at the repo root wires the four services together for local
development: PostgreSQL with a named volume for persistence, and health checks on every
service so `depends_on: condition: service_healthy` orders startup correctly.

## Data flow (target, built out phase by phase)

```
Repository (GitHub URL)
  → ingestion (secure clone into an isolated workspace)
  → file discovery / language detection (repository scanner)
  → static analysis (deterministic rules, no LLM)
  → scoring (category scores, overall score, prioritized findings)
  → AI interpretation (explanations, summaries, chat — enriches, never replaces the above)
  → report / dashboard
```

This separation between deterministic analysis and AI interpretation is a hard
architectural rule: the AI layer is only ever built on top of already-computed,
deterministic results, never used as a substitute for them.

## Environment variables

| Variable | Used by | Purpose |
|---|---|---|
| `DATABASE_URL` | backend | PostgreSQL DSN |
| `APP_SECRET` | backend | Symfony secret |
| `CORS_ALLOW_ORIGIN` | backend | Allowed origins regex for the frontend |
| `AI_ENGINE_BASE_URL` | backend | Base URL of the ai-engine service |
| `AI_ENGINE_INTERNAL_SECRET` | backend, ai-engine | Shared secret for backend→ai-engine auth (see [ai.md](ai.md#security)); empty disables the check |
| `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY` / `JWT_PASSPHRASE` | backend | JWT signing keypair |
| `AI_ENGINE_SERVICE_NAME` | ai-engine | Reported in `/health` |
| `AI_ENGINE_PROVIDER` | ai-engine | `mock` (default) or `anthropic` — see [ai.md](ai.md#provider-abstraction) |
| `ANTHROPIC_API_KEY` | ai-engine | Required only when `AI_ENGINE_PROVIDER=anthropic`; read only by ai-engine, never by the backend |
| `apiUrl` (`environment.ts`) | frontend | Base URL of the backend API |

## Ports (local development)

| Service | Port |
|---|---|
| frontend | 4200 |
| backend | 8000 |
| ai-engine | 8001 |
| postgres | 55432 (host) → 5432 (container) |

The PostgreSQL host port is remapped to `55432` because port 5432 is commonly already
bound by a locally installed PostgreSQL server; the container-to-container port (used by
the backend inside Docker) stays the standard `5432`.
