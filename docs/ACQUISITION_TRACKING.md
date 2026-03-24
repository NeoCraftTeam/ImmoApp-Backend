# Acquisition & UTM tracking

This document describes how **visitor sources** and **registration attribution** work end-to-end for KeyHome admins.

## Admin (Filament)

- **Analytique → Visites (UTM)** — raw `site_visits` rows (session, UTM parameters, inferred channel, device, linked user if any).
- **Analytique → Inscriptions par canal** — users with `acquisition_source`, UTM columns, filters, CSV export, and a shortcut to the full user record.
- **Dashboard** — existing acquisition stats plus **inscriptions par canal (30 jours)** and a doughnut chart (`RegistrationsByAcquisitionChart`).

## API

| Endpoint | Role |
|----------|------|
| `POST /api/v1/track/visit` | Anonymous; stores a visit (`session_id`, optional UTM, referrer, device). Throttled. |
| `POST /api/v1/auth/registerCustomer` / `registerAgent` | Optional `session_id` + UTM fields; attributes user and links prior visits for that session. |
| `POST /api/v1/auth/clerk/exchange` | Optional UTM + `session_id`; merged into Clerk pending payload for later `complete-profile`. |
| `POST /api/v1/auth/clerk/complete-profile` | Completes new Clerk user with the same attribution keys. |
| `POST /api/v1/auth/oauth/{provider}` | Optional UTM + `session_id` for token-based OAuth sign-up. |

## Frontend (Next.js)

- `UtmCaptureProvider` (in `src/app/providers.tsx`) runs on the client: reads UTM query params into `sessionStorage`, ensures a `session_id`, then calls `POST /track/visit` once per tab session after a successful response.
- `auth.service.ts` merges `getAttributionBodyForApi()` into email registration and Clerk complete-profile requests, then clears stored UTM keys (not the session id) after success.

## Classification

`App\Services\AcquisitionChannelClassifier` normalizes traffic into: `direct`, `organic`, `social`, `referral`, `paid`, `email` (aligned with `site_visits.source` and `users.acquisition_source`).

## CLI

Generate a campaign URL:

```bash
php artisan utm:generate --source=tiktok --medium=cpc --campaign=march_2026
```

Optional: `--base-url=`, `--content=`, `--term=`.

## Privacy

- Visits store a **hashed IP** (`ip_hash`), not the raw address.
- UTM values are marketing metadata; avoid placing personal data in UTM parameters.

## Filament / Livewire cache

If the admin dashboard shows `ComponentNotFoundException` for `registrations-by-acquisition-chart`, the panel’s **component cache** was built before that widget existed. Regenerate it:

```bash
php artisan filament:clear-cached-components
# then, if you normally cache for production:
php artisan filament:cache-components
```

Or in one step: `php artisan filament:optimize-clear` then `php artisan filament:optimize`.

## Related

See `docs/utm_tracking_implementation_guide.md` for the original full specification and checklist.
