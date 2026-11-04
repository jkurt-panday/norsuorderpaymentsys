# HANDOFF
**Date:** Wed Nov 04 2026

## Product and stack
- NORSU Order of Payment System: Laravel + Inertia v3 + React 19 + TypeScript + Tailwind v4.
- Design specification is in `DESIGN.md`; no `PRODUCT.md` exists.
- Pages are route-split in `resources/js/app.tsx`; authenticated pages use `AppSidebarLayout`.

## Current state
- P1 contrast/accessibility fixes are complete and archived at `plan/finished/fix_p1_contrast_and_a11y.md`.
- Sortable tables use semantic buttons/`aria-sort`; recipient pickers and public form errors have keyboard/ARIA support.
- Graduate and law ledger initial horizontal positioning and the Law Ledger nested-button hydration issue are fixed.
- Activity Log dark mode, header greeting, and Cloudflare proxy/mixed-content handling are fixed.
- Full UI audit remains at `plan/impeccable_audit_2026-10-30.md`; all five P1 findings were addressed.

## Active plans
- Frontend performance plan item 1 is complete for all five routes; items 2-5 remain pending: `plan/optimize_frontend_performance_2026-11-04.md`.
  - Reusable Puppeteer/CDP runner: `scripts/frontend-performance-baseline.mjs`; raw report: `tmp/performance/frontend-baseline.json`.
  - Runtime repaired: stale `public/fonts-manifest.dev.json` caused 5174 URLs; it was removed with stale `public/hot`, then one strict Vite was started on 127.0.0.1:5173.
  - All routes have 3/3 valid samples and zero request/HTTP/Vite/readiness/interaction failures; raw report has credentials provided and no serialized password.
  - Median LCP/transfer: login 1,524 ms/11,658.15 KB; opform 2,528 ms/28,534.00 KB; staff dashboard 704 ms/15,625.14 KB; assessments 1,268 ms/15,633.12 KB; law ledger 1,860 ms/14,156.31 KB.
  - `STAFF_SEED_PASSWORD` was absent, so the approved admin fallback was used consistently for all authenticated routes. Runner sidebar selector was minimally corrected.
  - Key finding: app/auth/settings layouts are eagerly imported by the shared entry despite page route splitting.
  - Oversized assets: auth JPG fallback is 2.18 MB; NORSU seal PNG is 307 KB at 52 px display height.
  - Repository rule: do not run `npm build` or any production build.
- Failing-suite repair plan awaits confirmation: `plan/fix_failing_test_suite_2026-11-03.md`.
- Later workflow work: `plan/core_workflow_edge_cases_and_e2e_test.md`.

## Constraints
- Always plan multi-page work and wait for confirmation.
- Keep this file under 60 lines and rewrite rather than append.
- Do not use Git commands without explicit permission.
