# HANDOFF — NORSU Order Payment System
_Updated: Sat Nov 14 2026_

## Stack
Laravel 13 · Inertia React · MySQL · S3 · PhpSpreadsheet · Pest · Browsershot

## Core Data
- `students` is shared by current Graduate and Law ledgers.
- `graduate_ledgers` and `law_school_ledgers` are separate transaction tables.
- `courses.course_college`: `Graduate School` or `School of Law`.
- `academic_terms` is shared.
- Entry types: `ar`, `payment`, `adjustment`.

## Import Pipeline
Both importers use two passes: collect distinct students/courses/terms, bulk-resolve
lookups, then map and insert ledger rows in 1,000-row chunks. Unknown values use
school-specific course and shared term fallbacks; blank-name rows are skipped.

## API Split Progress
- Phase 1 API infrastructure: complete.
- Phase 2A auth bootstrap + public OP/assessment API slices: complete.
- Phase 2B Fortify JSON contracts: complete.
- Phase 2C staff request API: complete.
- Phase 2D cashier API: complete.
- Phase 2E supporting documents API: complete.
- Phase 2F admin API: complete.
- Phase 2G master-data APIs: complete.
- Phases 2H ledger, 2I assessment, and 2J settings APIs: complete.
- OpenAPI contract updates and consolidated API regression validation: complete.
- Ledger bulk creation, student search, recipients, XLSX/PDF responses, queued email dispatch, and structured import API contracts: complete.
- Ledger import result adapter now returns exact imported/skipped/duplicate/warning counts through a shared `LedgerImportResult` contract; focused API tests pass.
- Browsershot statement jobs now log duration/success/failure, use hardened timeouts, and expose production PDF timeout/queue configuration in `.env.example`.
- New controllers: `Api/AuthController`, `Api/PublicOpFormController`,
  `Api/PublicAssessmentFormController`; existing web/Inertia flows intact.
- Public submissions return `reference_number` + HMAC `access_token`.
- Fortify `/login` → JSON `200 {message, data{id,name,email,role}}` when
  `Accept: application/json`; web redirects unchanged.
- Fortify `/logout` → JSON `204` when `Accept: application/json`.
- `/forgot-password` and `/reset-password` return generic JSON messages
  (no account enumeration). Reset emails point to `FRONTEND_URL/reset-password`.
- `Api\StaffRequestController` replaces all staff request 501 stubs: list,
  show/process/update, details/OR changes, student and term matching, and email.
- API tests: 45 tests, 135 assertions passed (PHPUnit class syntax).

## API Infrastructure
- Stateful Sanctum middleware, credentialed CORS, JSON exception envelopes,
  named rate limiters, versioned `/api/v1` routes, and `openapi.yaml` are present.
- Fortify JSON responses live in `app/Http/Responses`; legacy redirects remain.
- Core API feature groups are migrated; frontend scaffolding and initial authenticated feature workflows are now present in the sibling `/frontend` workspace. Legacy Inertia routes remain active.
- Frontend currently includes React Router, TanStack Query, Sanctum cookie API client, auth bootstrap/login flows, public form flows, staff/cashier/admin/document/master-data screens, and graduate/law ledger query, mutation, import/export, and balance workflows.
- OpenAPI TypeScript generation is configured with `pnpm run generate:api`; duplicate ledger path definitions were removed from the contract so generation succeeds.
- Ledger frontend now supports multipart CSV/XLS/XLSX import with structured imported/skipped/duplicate/warning results, authenticated binary export downloads, cache invalidation, and visible success/error states.
- Generated OpenAPI schema types now back auth users, ledger transaction requests, and import results; the OpenAPI import response documents the structured result contract.
- OpenAPI now documents both Graduate and Law ledger PDF, export, import, email-recipient, and queued-email contracts; frontend API coverage exercises both ledger variants.
- Ledger pages now expose authenticated statement PDF download and queued-email controls with visible pending, success, and error states.
- Frontend role authorization is centralized and tested for explicit allowlists; protected route behavior remains UX-only while Laravel authorization remains authoritative.
- Frontend API requests now have a bounded timeout (`VITE_API_TIMEOUT_MS`, default 15 seconds) while preserving caller `AbortSignal` cancellation for searches and changing filters.
- Logout now clears all cached server state, preventing authenticated ledger/documents/admin data from remaining available after a session ends.
- Sanctum CSRF bootstrap now has bounded timeout handling, propagates caller cancellation, validates failed bootstrap responses, and resets its cached promise so subsequent mutations can retry.
- Frontend deployment now includes an Apache/Hostinger `public/.htaccess` SPA fallback for React Router deep links; route regression tests cover public, ledger, staff, cashier, admin, and catch-all routes.
- API error parsing now safely handles empty, malformed, and non-JSON 4xx/5xx responses without leaking parser exceptions to the UI.
- SPA routing now renders an explicit not-found screen instead of silently redirecting unknown paths; duplicate admin master-data route registration was removed.
- Production-build SPA smoke validation is available through `frontend/pnpm smoke`; it verified 200 responses and the SPA shell for public, protected, nested, and unknown deep links.
- Playwright browser E2E scaffolding now covers public OP and assessment route/form rendering with mocked option endpoints; install Chromium with `pnpm e2e:install` before running `pnpm e2e`. Authenticated E2E remains dependent on a running Laravel API and test credentials.
- Consolidated backend API validation: `php artisan test tests/Feature/Api` passed — **60 tests, 265 assertions**.
- Consolidated frontend validation: OpenAPI generation passed; **12 test files, 25 tests passed**; TypeScript/Vite build and ESLint passed; production SPA smoke passed for public, protected, nested, and unknown deep links.
- Public OP and assessment standalone forms were adapted against the legacy `SubmitForm.tsx` and `AssessmentForm.tsx` behavior: API-backed options, multipart/JSON submission, server validation display, numeric field constraints, prefill persistence, reset actions, and receipt navigation are retained without moving the Inertia pages.
- Standalone `/public/opform` now uses a retained NORSU-branded layout with the legacy General/Student tabs, section headers, academic/payment fields, document picker, footer actions, responsive styling, and copied `finance_logo1.png`; legacy `/opform` remains available.
- Frontend validation after the OP design pass: **12 test files, 25 tests passed**; TypeScript/Vite build, ESLint, and SPA smoke passed.
- Standalone OP now has a dedicated responsive shadcn-inspired design layer with Lucide icons, section cards, General/Student tabs, academic/payment sections, document upload, and the Laravel-compatible `/public/opform` route alongside `/opform`.
- Standalone assessment now uses compatible Base UI/shadcn-style primitives (`@base-ui/react`, `clsx`, `tailwind-merge`, `class-variance-authority`) with the legacy branded header, card sections, icons, radio choices, academic selectors, validation, prefill, reset, and receipt navigation. The Laravel Inertia component remains unchanged.

## Local Data State
On Nov 12, 2026, local `norsu_ledger` was cleared: both ledger tables, students,
academic terms, and Graduate/Law courses are empty. One assessment form and five
public form submissions remain.

## Plans
- Canonical active plan: `plan/laravel_react_api_decoupling.md`.
- The former Phase 2B continuation plan was consolidated into the canonical plan.
- Completed: `plan/finished/optimize_graduate_import.md` (Nov 13, 2026).

## Next Action
Next action: provision a browser runner and production-like Laravel/API environment for authenticated E2E verification. Required checks include Sanctum cookies/CSRF, login/logout, role boundaries, public submissions, staff/cashier workflows, documents, admin/master data, ledger imports/exports/PDF/email, and rollback/cutover readiness. Legacy Inertia routes remain active until parity and production verification.
