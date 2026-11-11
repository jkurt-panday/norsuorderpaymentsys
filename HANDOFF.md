# HANDOFF
**Date:** Tue Nov 10 2026

## Product and stack
- NORSU Order of Payment System: Laravel + Inertia v3 + React 19 + TypeScript + Tailwind v4.
- Design specification is in `DESIGN.md`; no `PRODUCT.md` exists.
- Pages are route-split in `resources/js/app.tsx`; authenticated pages use `AppSidebarLayout`.

## Current state
- Multi-Student Entry (formerly Cohort Entry) UI labels updated across Law & Graduate Ledgers; archived to `plan/finished/rename_cohort_to_multistudent_entry.md`.
- Backend endpoints and internal logic maintain stable `cohort` naming (`cohortStore`, `cohort_rows`).
- P1 contrast/accessibility fixes complete; archived at `plan/finished/fix_p1_contrast_and_a11y.md`.
- Full UI audit remains at `plan/impeccable_audit_2026-10-30.md`; all five P1 findings addressed.
- **User Manuals Suite (Markdown & PDF):**
  - Omnibus manual: `manual/USER_MANUAL.md` & `manual/USER_MANUAL.pdf` (27 pages).
  - 4 Role-tailored manuals completed with "What You Are Able to Do as [Role]" sections, "How to..." guides, zero emojis, zero blank pages:
    1. Payor Guide: `manual/USER_MANUAL_PAYOR.md` & `.pdf` (14 pages, 946.1 KB)
    2. Accountant Guide: `manual/USER_MANUAL_ACCOUNTANT.md` & `.pdf` (18 pages, 397.3 KB)
    3. Cashier Guide: `manual/USER_MANUAL_CASHIER.md` & `.pdf` (10 pages, 212.5 KB)
    4. Admin Guide: `manual/USER_MANUAL_ADMIN.md` & `.pdf` (11 pages, 268.2 KB)
  - Archived to `plan/finished/four_role_manuals_plan.md`.

## Active plans
- Security hardening: `plan/owasp_zap_security_headers_hardening.md`.
- Workflow testing: `plan/core_workflow_edge_cases_and_e2e_test.md`.
- Ledger modal: `plan/graduate_ledger_import_skip_error_modal.md`.

## Constraints
- Always plan multi-page work and wait for confirmation.
- Keep this file under 60 lines and rewrite rather than append.
- Do not use Git commands without explicit permission.
- Do not run npm build.
