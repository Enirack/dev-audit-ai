We are now starting **PHASE 3 — REPOSITORY SCANNER**.

First verify all previous functionality.

Do not implement AI yet.

## Goal

Build a deterministic repository scanner.

Input:

A successfully ingested repository.

Output:

A structured repository inventory.

## Scanner responsibilities

The scanner must recursively inspect the repository without executing project code.

Collect:

- files
- directories
- file sizes
- extensions
- programming languages
- line counts
- binary files
- ignored files
- configuration files
- test files
- documentation files

## Ignore rules

Do not analyze:

- .git
- node_modules
- vendor
- dist
- build
- coverage
- cache directories
- binary files
- generated files where safely identifiable

Make the ignore system configurable.

## Language detection

Initially support:

JavaScript
TypeScript
PHP
Python
HTML
CSS
JSON
YAML
Markdown

Return language statistics.

Example:

{
  "languages": {
    "TypeScript": {
      "files": 42,
      "lines": 12450
    },
    "PHP": {
      "files": 31,
      "lines": 8320
    }
  }
}

## Repository metadata

Detect when possible:

- package.json
- composer.json
- requirements.txt
- pyproject.toml
- package-lock.json
- Dockerfile
- docker-compose files
- README
- test directories
- CI configuration
- environment files

Never expose secrets found in environment files.

## Scan output

Create a structured scan result.

The result should be persisted.

Design the model so future analysis modules can consume it.

## Performance

The scanner must:

- stream large files where possible
- avoid loading the entire repository into memory
- enforce file-size limits
- enforce repository-size limits
- avoid following unsafe symbolic links

## Tests

Test:

- nested directories
- ignored directories
- large files
- binary files
- language detection
- line counting
- malformed files
- symbolic links where applicable

Document the scanner architecture.

Do not implement scoring or AI yet.