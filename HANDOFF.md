# HANDOFF
**Date:** Fri Oct 23 2026

## Current State
Public (unauthenticated) pages under `/public/*` are now forced to light mode; the dark-mode theme toggle has been removed from those pages and dark flash on first paint is prevented. Dark mode remains active everywhere else (login, admin, staff, cashier, client, ledgers).

## Done
- [x] Public pages light-only: `SubmitForm.tsx`, `AssessmentForm.tsx` (toggle removed)
- [x] `HandleAppearance` middleware forces `appearance=light` for `/public/*`
- [x] `use-appearance.tsx` never applies `dark` on public routes (no flash)
- [x] `PublicLayout` defensively strips `dark` + sets `color-scheme: light` on mount

## Next Steps
- Verify in browser (`npm run dev`) for each `/public/*` route with appearance=dark and OS dark mode; confirm `/login` still goes dark.

## Decisions & Files Touched
- `app/Http/Middleware/HandleAppearance.php` — path-aware appearance
- `resources/js/hooks/use-appearance.tsx` — `isPublicRoute()` guard in `applyTheme`
- `resources/js/pages/layouts/PublicLayout.tsx` — mount guard
- `resources/js/pages/public/SubmitForm.tsx` — removed `ThemeToggle`
- `resources/js/pages/public/AssessmentForm.tsx` — removed `ThemeToggle`
- `resources/js/components/theme-toggle.tsx` — UNCHANGED (still used by auth layouts + admin header)
