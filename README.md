# DevAudit AI

DevAudit AI is a platform that audits GitHub repositories: it ingests a repository, runs static analysis, scores it, and produces an AI-assisted audit report through a professional dashboard.

## Architecture

Monorepo with four services:

```
dev-audit-ai/
├── backend/        Symfony 8 API (PHP 8.4, Doctrine ORM, PostgreSQL)
├── frontend/        Angular dashboard
├── ai-engine/        FastAPI service for AI-driven analysis
├── infrastructure/  Docker Compose and deployment config
└── docs/            Phase-by-phase project specification
```

- **backend** — REST API: authentication (JWT), repository ingestion, audits, scoring.
- **frontend** — Angular dashboard consuming the backend API.
- **ai-engine** — Python/FastAPI service reached by the backend over HTTP for AI-driven analysis.
- **infrastructure** — Docker Compose wiring PostgreSQL, backend, frontend and ai-engine together.

## Current status

The project follows the phased plan in [`docs/`](docs). As of now:

- **Backend**: scaffolded (Symfony 8, Doctrine, JWT auth via Lexik, CORS). Implements `GET /api/health`, `POST /api/register`, `POST /api/login`, `GET /api/me`, and basic repository import endpoints (`/api/repositories`). Entities exist for repositories, scans, audits, findings and scores. No Doctrine migrations generated yet.
- **Frontend**: not started.
- **AI Engine**: not started.
- **Infrastructure**: no Docker Compose setup yet.

## Local development

### Backend

```bash
cd backend
composer install
php bin/console doctrine:migrations:migrate
symfony server:start
```

Requires PHP 8.4+, Composer, and a PostgreSQL instance (configure `DATABASE_URL` in `backend/.env.local`).

## License

Proprietary.
