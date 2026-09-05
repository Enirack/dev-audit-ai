We are now starting **PHASE 1 — FOUNDATION**.

Do not implement GitHub ingestion, static analysis or AI yet.

## Goal

Create a stable monorepo foundation for DevAudit AI.

Expected structure:

dev-audit-ai/
├── frontend/
├── backend/
├── ai-engine/
├── infrastructure/
├── docs/
├── .github/
├── docker-compose.yml
└── README.md

## Backend

Create the Symfony 8 API application.

Configure:

- PHP 8.4+
- Doctrine ORM
- PostgreSQL
- API structure
- environment variables
- migrations
- validation
- logging
- health endpoint

Create:

GET /api/health

Expected response:

{
  "status": "ok"
}

## Frontend

Create Angular 22 application.

Create a minimal application shell.

Create:

- routing
- environment configuration
- API service
- basic health-check integration
- error handling

Create a simple home/dashboard placeholder.

## AI Engine

Create FastAPI service.

Create:

GET /health

Expected response:

{
  "status": "ok"
}

Do not implement AI functionality.

## Infrastructure

Create Docker Compose for:

- PostgreSQL
- Symfony backend
- Angular frontend
- FastAPI analysis engine

Use environment variables.

Do not hardcode secrets.

Add health checks where appropriate.

## Documentation

Create:

/docs/architecture.md
/docs/development.md

Document:

- architecture
- responsibilities of each service
- local development
- environment variables
- service ports
- how services communicate

Update README.md with:

- project description
- architecture
- stack
- local installation
- development commands

## Quality

Before finishing:

- start Docker
- verify PostgreSQL
- verify Symfony
- verify Angular
- verify FastAPI
- test health endpoints
- run available tests
- fix all startup errors

Do not proceed to Phase 2 automatically.

At the end, provide:

1. Files created
2. Services running
3. Tests executed
4. Problems encountered
5. Problems fixed
6. Recommended next phase