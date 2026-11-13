# HANDOFF — NORSU Order Payment System
_Updated: Fri Nov 13 2026_

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
- Phase 2C staff request API: complete; Phase 2D cashier API is next.
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
- Remaining feature groups beginning with cashier still return 501 stubs.

## Local Data State
On Nov 12, 2026, local `norsu_ledger` was cleared: both ledger tables, students,
academic terms, and Graduate/Law courses are empty. One assessment form and five
public form submissions remain.

## Plans
- Canonical active plan: `plan/laravel_react_api_decoupling.md`.
- The former Phase 2B continuation plan was consolidated into the canonical plan.
- Completed: `plan/finished/optimize_graduate_import.md` (Nov 13, 2026).

## Next Action
Phase 2D when resumed: replace `cashier/requests/*` 501 stubs with a JSON API.
