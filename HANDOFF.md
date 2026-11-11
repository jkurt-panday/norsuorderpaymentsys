# HANDOFF
**Date:** Wed Nov 11 2026

## Product and stack
- NORSU Order of Payment System: Laravel + Inertia v3 + React 19 + TypeScript + Tailwind v4.
- Roles: Staff, Cashier, Admin. Payors access public OP forms without authentication.
- Pages are route-split in `resources/js/app.tsx`; authenticated pages use `AppSidebarLayout`.

## Current state
- Fully removed speculative `client` role and portal backend routes, middleware branches, controllers, and UI layouts.
- Payors documented & implemented as 100% public (no account or login required).
- User manuals compiled without emojis or placeholders.
- Security scanning pipeline (`.github/workflows/security.yml`) complete and hardened.
- Excel templates generated for Law & Graduate ledgers (`plan/law_ledger_import_template.xlsx`, `plan/graduate_ledger_import_template.xlsx`).
- Law & Graduate imports enhanced: supports student number and email (aliases & validation), matches by student number or name, non-destructively backfills missing student data without overwriting existing data, preserves legacy positional imports.

## Active plans
- Comprehensive audit (read-only): `plan/comprehensive_audit_2026-11-11.md`
- Fix UACS Forms: `plan/fix_uacs_create_and_edit_forms.md`
- Security hardening: `plan/owasp_zap_security_headers_hardening.md`
- Workflow testing: `plan/core_workflow_edge_cases_and_e2e_test.md`
- Ledger modal: `plan/graduate_ledger_import_skip_error_modal.md`

## Finished plans
- Remove Client Role: `plan/finished/remove_client_role_and_portal.md` (Completed: 2026-11-11).
- Excel Templates & Import Support: `plan/finished/excel_templates_and_import_support.md` (Completed: 2026-11-11).

## Constraints
- Always plan multi-page work and wait for confirmation.
- Keep this file under 60 lines and rewrite rather than append.
- Do not use Git commands without explicit permission.
- Do not run npm build.
