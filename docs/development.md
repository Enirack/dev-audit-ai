# Local development

## Prerequisites

- Docker + Docker Compose
- (for running services outside Docker) PHP 8.4+, Composer, Node.js 22+, Python 3.12+

## Quick start (Docker)

```bash
docker compose up -d --build
```

This starts, in order (via health checks):

1. `postgres` — PostgreSQL 16, data persisted in the `postgres_data` named volume.
2. `ai-engine` — FastAPI service on `:8001`.
3. `backend` — Symfony API on `:8000`. Runs pending Doctrine migrations on container start, then serves via PHP's built-in server.
4. `frontend` — Angular dev server on `:4200`.

Verify:

```bash
curl http://localhost:8000/api/health
curl http://localhost:8001/health
curl -I http://localhost:4200
```

## Backend (outside Docker)

```bash
cd backend
composer install
php bin/console lexik:jwt:generate-keypair --skip-if-exists
DATABASE_URL="postgresql://devaudit:devaudit@127.0.0.1:55432/devaudit?serverVersion=16&charset=utf8" \
  php bin/console doctrine:migrations:migrate
DATABASE_URL="postgresql://devaudit:devaudit@127.0.0.1:55432/devaudit?serverVersion=16&charset=utf8" \
  php -S 0.0.0.0:8000 -t public
```

Run tests:

```bash
cd backend
composer install
php bin/phpunit
```

### Known dependency caveat

`doctrine/orm` (^3.6) and `doctrine/dbal` (^4.4, the latest stable release — `^4.5` is not
yet released) disagree on the DBAL `Schema::edit()` API: several of `symfony/doctrine-bridge`'s
built-in schema listeners (DBAL cache adapter, remember-me token provider, lock store, PDO
session handler — none of which this app uses) unconditionally call
`GenerateSchemaEventArgs::setSchema()`, which throws unless `doctrine/dbal ^4.5` is
installed. `src/Kernel.php` neutralizes those four listeners (swaps their class for a
no-op, see the inline comment there) so `doctrine:schema:update` /
`doctrine:migrations:diff` work with the currently available dbal version. This can be
removed once `doctrine/dbal ^4.5` reaches a stable release.

## Frontend (outside Docker)

```bash
cd frontend
npm install
npm start            # ng serve, http://localhost:4200
npm run build        # production build
npm test             # unit tests (vitest via `ng test`)
```

## AI Engine (outside Docker)

```bash
cd ai-engine
python -m venv .venv
.venv/Scripts/activate   # Windows; `source .venv/bin/activate` on macOS/Linux
pip install -r requirements-dev.txt
uvicorn app.main:app --reload --port 8001
pytest
```

> Note: on very new Python versions without prebuilt wheels yet for some dependencies,
> prefer running ai-engine through Docker (`python:3.12-slim` base image) rather than a
> local venv.

## Database access

The Postgres container exposes port `55432` on the host (see
[architecture.md](architecture.md#ports-local-development) for why). To connect with `psql`:

```bash
psql -h 127.0.0.1 -p 55432 -U devaudit -d devaudit
```
