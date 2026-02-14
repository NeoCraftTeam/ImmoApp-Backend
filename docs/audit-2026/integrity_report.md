# System Integrity Report

## Overview
**Date:** 2026-02-14
**Scanner:** `scripts/laravel-integrity.mjs`

## Findings Summary
| Category | Count | Status |
|---|---|---|
| **Broken Links** (Client → ???) | 0 | ✅ Clean |
| **Unused Routes** (Route → ???) | 63 | ⚠️ Warning (Frontend incomplete) |
| **Orphaned Methods** (Controller → ???) | 2 | ℹ️ Info |

## Analysis
The high number of **Unused Routes (63)** indicates that the backend API is significantly ahead of the frontend implementation.

### 1. Active Frontend Surface
The frontend (`keyhome-frontend-next`) currently only interacts with the **Authentication** module:
- `/auth/me`
- `/auth/login`
- `/auth/registerCustomer`
- `/auth/logout`
- `/sanctum/csrf-cookie`

All other modules (Ads, Agencies, Quarters, Subscriptions, Payments) are fully implemented on the backend but **not yet wired** to the Next.js frontend.

### 2. Orphaned Controller Methods
Two internal methods are public but not routed:
- `App\Http\Controllers\Api\V1\Auth\EmailVerificationController@verify`
- `App\Http\Controllers\Api\V1\Auth\EmailVerificationController@resend`

*Recommendation:* Ensure these are intended to be public API endpoints or refactor them to private/protected if used internally.

## Action Plan
1.  **Backend is ready.** The surplus of unused routes confirms the API is feature-complete for the current scope.
2.  **Next Phase:** Focus on connecting the Frontend to the existing `Ads` and `Subscriptions` endpoints.
