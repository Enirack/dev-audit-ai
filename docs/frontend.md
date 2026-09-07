# Phase 7 — Frontend architecture

## Stack

Angular 22, standalone components everywhere (no NgModules), signals for
component state, the new `@if`/`@switch`/`@for`/`@let` control-flow syntax,
and plain SCSS with CSS custom properties for the design system — no UI
framework (no Material, no Tailwind), matching the minimal-dependency
approach the rest of the project already follows.

## Structure

```
src/app/
  core/
    models/        TS interfaces mirroring backend DTOs (see below)
    services/       AuthService, RepositoryService, ScanService, AuditService, AiService
    interceptors/    authInterceptor — attaches the JWT, handles 401 → logout + redirect
    guards/          authGuard (protected routes), guestGuard (login/register when already authed)
    testing/         fake-local-storage.ts — see "Testing" below
  shared/
    ui/              SeverityBadge, ScanStatusBadge, ScoreGauge, CategoryScoreList, EmptyState
    utils/format.ts  category/severity labels, score-color thresholds, date/byte formatting
  layout/
    app-shell/       authenticated shell: top nav, user menu, <router-outlet>
  features/
    landing/         public marketing page
    auth/            login, register (share auth.scss)
    dashboard/       repositories, latest audit per repo, activity, critical findings
    repository-detail/  repo info, language distribution, scan/audit history, start-audit button
    audit-overview/  score, category scores, severity distribution, AI executive summary
    findings/        findings-list (filter/search/pagination), finding-detail (+ AI explanation)
    ai-assistant/    repository-aware chat
```

## Auth

JWT stored in `localStorage` (`devaudit_token`). `AuthService` exposes
`token`/`user`/`isAuthenticated` as signals. `authInterceptor` attaches
`Authorization: Bearer <token>` to every request targeting the API base URL,
and on a 401 from any endpoint other than `/login`/`/register` itself, logs
out and redirects to `/login?returnUrl=<current>`. `authGuard` protects the
authenticated route tree; `guestGuard` keeps a logged-in user off
`/login`/`/register`.

## Design system

Dark, information-dense "developer tool" aesthetic (`src/styles.scss`):
CSS custom properties for color/spacing/radius/typography, a small set of
reusable utility classes (`.card`, `.btn`, `.badge`, `.input`, `.state-panel`,
`.skeleton`), and a fixed severity/score color mapping used everywhere
(`shared/utils/format.ts`'s `scoreColor`/severity badge colors) so a
"critical" or a "40-ish score" always reads the same color across every
page. No chart library — the score gauge is a CSS `conic-gradient` ring and
the language-distribution chart is a single flex bar; both are cheap enough
in pure CSS that pulling in a charting dependency wasn't justified.

## Two small backend additions made during this phase

Both were discovered as real gaps while building the pages that needed them
— not scope creep, but the API genuinely didn't support the page:

1. **`GET /api/audits/{id}/findings/{findingId}`** (`AuditController`) — the
   finding-detail page needs to fetch one finding by id, and no such
   endpoint existed (only the paginated list). Added with the same
   ownership check pattern already used for `/explain`.
2. **`AuditResponse.repositoryId` / `repositoryName`** — the audit overview
   page needs to link back to its repository (breadcrumb, "back to repo"),
   but `AuditResponse` only exposed `repositoryScanId`. Added both fields,
   sourced from the same `Audit → RepositoryScan → Repository` chain the
   controller's ownership check already traverses.

## Known trade-off: dashboard aggregation is client-side

The dashboard shows each repository's latest scan status and audit score,
plus a critical-findings summary. There is no backend aggregate endpoint for
this — the dashboard fetches the repository list, then for each repository
fetches its scan list and (if completed) its audit, via RxJS `forkJoin`.
This is O(2N) requests for N repositories. Acceptable for v1's expected
repository counts; if this becomes a real multi-repository dashboard at
scale, a dedicated `GET /api/dashboard` aggregate endpoint would be the
right fix — documented here rather than built speculatively now.

## Testing

`ng test` (vitest). The test environment here exposes Node's own
experimental `localStorage` global (non-functional without
`--localstorage-file`) instead of a working jsdom one, so any spec touching
`AuthService`'s token storage stubs it via
`core/testing/fake-local-storage.ts` (`installFakeLocalStorage()` +
`vi.unstubAllGlobals()` in `afterEach`) rather than relying on the real
global.

Covered: `AuthService` (login/logout/token persistence/rehydration),
`authGuard`/`guestGuard` (redirect behavior), `authInterceptor` (token
attachment, 401 handling, login/register exemption), `Dashboard` (loading/
empty/error/populated states), `FindingsList` (initial load, empty state,
category filter refetch, pagination bounds).

The full page-to-page flow (login → dashboard → repository → audit →
findings → finding detail → AI assistant → logout → guard redirect) was
verified manually in a real browser against the live Docker stack rather
than with end-to-end test automation. This caught a real bug unit tests
alone would have missed: the client-side URL pattern was never the problem
(a syntactically valid GitHub URL for a repository that doesn't exist is,
correctly, a valid-looking URL) — the backend correctly returned "Repository
not found or is private," but the frontend's generic 422 handler mislabeled
it as "invalid URL format." Fixed by surfacing the backend's actual error
message instead of guessing one client-side.
