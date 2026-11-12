# HANDOFF
**Date:** Thu Nov 12 2026

## Product and stack
- NORSU Order of Payment System: Laravel + Inertia v3 + React 19 + TypeScript + Tailwind v4.
- Roles: Staff, Cashier, Admin. Payors access public OP forms without authentication.
- Pages are route-split in `resources/js/app.tsx`; authenticated pages use `AppSidebarLayout`.

## Current state
- Git-history-based OJT journal created at `OJT_JOURNAL.md`, covering Rohan/Firenadoed commits from July 14–November 12, 2026.
- Fully removed speculative `client` role and portal backend routes, middleware, and UI layouts.
- Payors documented & implemented as 100% public (no account or login required).
- Security scanning pipeline (`.github/workflows/security.yml`) complete and hardened.
- Excel templates generated for Law & Graduate ledgers with Column Q `Input By`.
- Law & Graduate imports enhanced: supports student number, email, and attribution.
- Philippine DPA (RA 10173) & NPC compliance audit completed.
- Manual table reload with 10-second cooldown (`useReloadCooldown`) in place.
- Password reset enabled with Fortify backend, split layout Inertia views, and passing tests.
- `/` is ordered Hero → Features → Contact → About the Team strip (student-intern copy + reveal button, people icon) → branded footer. Features uses equal-width OP/Assessment tabs; Contact lists the NORSU Main Campus Accounting Office and email.

## Active plans
- SPA + Laravel API split (big-bang): `plan/spa_api_split_big_bang.md`
- Comprehensive audit (read-only): `plan/comprehensive_audit_2026-11-11.md`
- Fix UACS Forms: `plan/fix_uacs_create_and_edit_forms.md`
- Security hardening: `plan/owasp_zap_security_headers_hardening.md`
- Workflow testing: `plan/core_workflow_edge_cases_and_e2e_test.md`
- Ledger modal: `plan/graduate_ledger_import_skip_error_modal.md`

## Finished plans
- Landing page: `plan/finished/landing_page_implementation_plan.md` (Completed: 2026-11-12).
- Forgot Password Feature: `plan/finished/forgot_password_feature.md` (Completed: 2026-11-11).
- Graduate Ledger Restore Input By Column Q: `plan/finished/graduate_ledger_restore_input_by_column_q.md` (Completed: 2026-11-11).
- Manual Table Reload with 10s Cooldown: `plan/finished/remove_usepoll_and_add_manual_reload_cooldown.md` (Completed: 2026-11-11).
- Philippine DPA & Security Audit: `plan/finished/philippine_dpa_compliance_and_security_audit_plan.md` (Completed: 2026-11-11).
- Remove Client Role: `plan/finished/remove_client_role_and_portal.md` (Completed: 2026-11-11).
- Excel Templates & Import Support: `plan/finished/excel_templates_and_import_support.md` (Completed: 2026-11-11).

## Constraints
- Always plan multi-page work and wait for confirmation.
- Keep this file under 60 lines and rewrite rather than append.
- Do not use Git commands without explicit permission.
- Do not run npm build.
