We are now starting **PHASE 2 — GITHUB REPOSITORY INGESTION**.

First inspect Phase 1 and verify that the entire stack works.

Do not implement static analysis or AI yet.

## Goal

Allow DevAudit AI to accept a public GitHub repository URL and securely retrieve its source code for analysis.

Example:

https://github.com/username/project

## Backend domain

Implement:

User
Repository
RepositoryScan

Repository should contain at minimum:

- id
- name
- url
- provider
- owner
- defaultBranch
- createdAt
- updatedAt

RepositoryScan should contain:

- id
- repository
- status
- startedAt
- completedAt
- errorMessage

Statuses:

- pending
- cloning
- scanning
- completed
- failed

Use Doctrine entities and migrations.

## API

Create an endpoint similar to:

POST /api/repositories

Request:

{
  "url": "https://github.com/example/project"
}

Validate:

- valid URL
- GitHub hostname
- repository URL format

Do not allow arbitrary URLs.

## GitHub ingestion

Implement a secure repository ingestion service.

For V1:

- support public GitHub repositories
- retrieve the repository
- identify the default branch
- clone/download the repository into a controlled temporary workspace

Security requirements:

- prevent path traversal
- prevent command injection
- isolate temporary repositories
- never execute repository code
- never trust repository filenames
- enforce repository size limits
- enforce timeout limits
- clean temporary files after processing

Do not run npm install, composer install, pip install or any project scripts.

Only retrieve source files.

## API

Create:

POST /api/repositories/{id}/scans

This creates a RepositoryScan.

The scan must initially perform only ingestion.

Return the scan ID and status.

## Important

Do not build the complete scanner yet.

The objective is simply:

GitHub URL
→ repository validation
→ source retrieval
→ controlled workspace
→ scan record

## Testing

Create tests for:

- valid GitHub URL
- invalid URL
- non-GitHub URL
- malformed repository URL
- duplicate repository
- ingestion failure
- cleanup
- path traversal protection

Actually test the ingestion process with a small public repository.

Document the ingestion architecture.

Do not proceed to Phase 3 automatically.