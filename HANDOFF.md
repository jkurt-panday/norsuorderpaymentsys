# HANDOFF
## Current State
- Auth login page redesigned with split layout and brand panel.
- NORSU campus photo added behind brand panel gradient overlay in `resources/js/pages/auth/login.tsx`.

## Done
- [x] Wire campus image (`/images/norsu-campus.jpg`) inside `<aside>` in `resources/js/pages/auth/login.tsx`.
- [x] Maintain `-z-20` layer order with existing `-z-10` gradient overlay.
- [x] Verified with `npx tsc --noEmit`.

## Next Steps
- Verify visual appearance in browser when dev server is running.

## Decisions & Files Touched
- `resources/js/pages/auth/login.tsx`: Added campus image background div with `-z-20`, `saturate(1.1)`, and `58% 30%` positioning.
