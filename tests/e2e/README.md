# Playwright templates (top 5 flows)

These files are **templates** for Phase 11 certification. They use the same Playwright style as `tests-e2e/` and do not add webpack, Vite, or Angular 2+.

## How to run (existing project)

From the repo root, use the existing e2e runner that already powers `tests-e2e/*.spec.ts`. Point specs at `tests/e2e/` or copy a template next to `tests-e2e/smoke-safe.spec.ts`.

Staging env (after `sql/tep_staging_seed.sql`):

- `E2E_LOGIN_ACCOUNT=Phoenix Enterprise Events`
- `E2E_LOGIN_EMAIL=admin@phoenix-enterprise.example`
- `E2E_LOGIN_PASSWORD=TepStaging!1000`
- `E2E_PUBLIC_EVENT_SLUG=phoenix-summit-2026`

## Templates

1. `01-staff-login-dashboard.spec.ts` — admin login + dashboard cards
2. `02-staff-checkin.spec.ts` — name/ticket search and check-in toggle
3. `03-live-poll-vote.spec.ts` — Live poll tap + Event Pulse
4. `04-vendor-lead-capture.spec.ts` — Vendor Operations save lead
5. `05-public-registration-discount.spec.ts` — public register + SUMMIT10 math (seed `method=amount`, due **$834.00** for VIP + lunch)
