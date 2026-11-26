# HANDOFF — NORSU Order Payment System
_Updated: Thu Nov 26 2026_

## Core Standard & Philosophy
Strict adherence to modern web standards across UX, DX, and AX (responsive, accessible, clean architecture, REST/CORS contracts).

## Stack & Architecture
- **Backend:** Laravel 13 modular monolith (`/norsuorderpaymentsys`), MySQL, Fortify auth, Sanctum SPA cookies, DomPDF/Browsershot.
- **PDF note:** Browsershot needs project-local `node_modules` (pnpm). If repo folder is moved/renamed, rerun `pnpm install` or PDF generation fails with `Cannot find module 'puppeteer'`.
- **Frontend:** Standalone React 19 / Vite SPA (`/norsuorderpaymentsys_frontend`), React Router v7, TanStack Query v5, Tailwind v4 / shadcn UI.
- Contract defined via `openapi.yaml`. TypeScript schemas via `pnpm run generate:api`.

## Core Data & Rules
- `students` table is shared between Graduate and Law ledgers.
- `graduate_ledgers` and `law_school_ledgers` are separate transaction tables.
- `courses.course_college`: `Graduate School` or `School of Law`. Shared `academic_terms`.
- Importers use chunked (1,000-row) two-pass bulk resolution.
- `assessment_forms.enrolled_under` is an independent form value (default "Undergraduate" in both public forms), NOT derived from the student record or course college; backend stores it verbatim and `LedgerMatchingService` needs exactly "Graduate"/"School of Law" to pick a ledger. Staff detail page shows it read-only.

## Current State & API Parity (100% Ported)
- **Backend API v1:** Endpoints completed for auth, public OP & assessments, staff operational requests & dashboard metrics, cashier workflows, supporting documents, admin user & activity management, master data CRUD, ledgers (graduate & law), balance calculations, PDF statements.
- **Dedicated Staff Master Data Pages:** 100% ported with `ResourceTable` parity (Bank Accounts, Payment Options, Memberships, Authorized Officials, UACS, Courses).
- **Staff Assessments:** 100% ported (AssessmentIndexPage, AssessmentDashboardPage, SOA PDF print, email SOA modal).
- **Ledger Statement Printer:** 100% ported (`PrintSelectPage` supporting Graduate and Law ledgers).
- **Ledger pagination:** Graduate/Law tables pin page 1 and the last page, with sliding 3-page window.
- **Ledger statement PDF timeout:** Both `generatePdf()` controllers call `set_time_limit(360)`.
- **Ledger statement emails are synchronous:** `Api\LedgerController::sendEmails()` uses `dispatch_sync()`.
- **Staff Dashboard & Activity Log:** 1-to-1 with monolith (StatCards, Recharts, today-filtered requests, audit log).
- **Ledger Transactions (SPA):** Single, Batch, Cohort forms; inline student registration; scholarships/latin honors inline & via row actions.
- **Ledger Transaction Edit & Show API:** `GET /{id}` and `PUT /{id}` in `LedgerController` working with correct route parameter resolution; edit mode submission guard in `LedgerTransactionForm` hardened against missing record IDs.
- **Auto-select course & term on student pick:** Selecting a student in Single or Batch forms automatically sets their last-used course in that ledger and the latest academic term (sorted chronologically: latest year first, Summer > 2nd Sem > 1st Sem).
- **Ledger Excel/PDF & import timeouts:** Scoped 6-minute client timeout.
- **Ledger blank/continuation import rows:** Empty template rows skipped, continuation rows inherit context.
- **Test Suite:** Vitest and Laravel test suites (`LedgerCrudTest`, `LedgerImportTest`, etc.) passing cleanly.
- **Staff OP Student Matching & Unlink:** Link, unlink, and create+link students for ledger courses in SPA detail dialog with inline errors (monolith parity).
- **Ledger data cleared 2026-11-26:** graduate/law ledgers, students, academic terms and GS/Law courses are empty; `assessment_forms.course_id` is `ON DELETE CASCADE`, so deleting courses also deletes referencing assessments.

## Next Actions
- Verify ledger transaction edit in SPA: open Graduate or Law ledger, click edit on a row, update particulars/amount, save → verify 200 OK and updated table row.
