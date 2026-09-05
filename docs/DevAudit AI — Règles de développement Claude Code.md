# DevAudit AI — Development Rules

You are working on **DevAudit AI**, an AI-powered codebase auditing platform.

This is a serious public GitHub portfolio project.

Your priority is NOT to maximize the number of features.

Your priority is:

- correctness
- maintainability
- security
- clean architecture
- good developer experience
- professional UX
- testability
- documentation
- production readiness

## Critical development rule

We will build the project incrementally.

The development phases are:

1. Foundation
2. GitHub ingestion
3. Repository scanner
4. Static analysis
5. Scoring system
6. AI layer
7. Professional UI
8. Deployment and final polish

NEVER skip directly to a later phase.

Before starting a phase:

1. Inspect the current implementation.
2. Understand what already exists.
3. Run the existing application.
4. Run the existing tests.
5. Identify broken functionality.
6. Fix relevant problems before adding new functionality.

After each phase:

1. Run tests.
2. Run linters/type checks where available.
3. Verify Docker services.
4. Verify API endpoints.
5. Verify the frontend.
6. Update documentation.
7. Report exactly what was implemented.
8. Report remaining technical debt or blockers.

## Important

Do not rewrite working code without a strong reason.

Do not create unnecessary abstractions.

Do not install dependencies unless they are actually required.

Do not use mock data when implementing a real feature unless explicitly requested.

Do not claim a feature works unless you have actually tested it.

When an architectural decision is important, document it in `/docs`.

## Target stack

Frontend:
- Angular 22
- TypeScript

Backend:
- Symfony 8
- PHP 8.4+
- Doctrine ORM
- PostgreSQL
- REST API
- JWT authentication

Analysis engine:
- Python
- FastAPI

Infrastructure:
- Docker
- Docker Compose

The AI layer will be added only after the deterministic analysis pipeline works.

## Supported languages for V1

- JavaScript
- TypeScript
- PHP
- Python

## Product principle

DevAudit AI must NOT become a simple chatbot.

The system must first collect objective information from a repository.

The AI will later interpret those structured results.

Architecture:

Repository
→ ingestion
→ file discovery
→ language detection
→ static analysis
→ metrics
→ findings
→ scoring
→ AI interpretation
→ report

Keep this separation throughout the project.