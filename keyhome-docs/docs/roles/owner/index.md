---
sidebar_position: 1
title: "Owner Role"
---

# Owner / Bailleur Role

## Description

An **Owner** (also *Bailleur* or *Agent* in the UI, `UserRole.AGENT` in the codebase) is an authenticated user who lists and manages properties on KeyHome. Owners can be **individuals** (`UserType.INDIVIDUAL`) or **agencies** (`UserType.AGENCY`).

The Owner panel lives entirely under the `/owner/*` route group, which uses its own layout (`OwnerThemeProvider`), its own authentication flow, and is protected by a role cookie set by the middleware.

---

## Permissions

| Action | Allowed |
|---|---|
| Create / edit / delete listings | |
| Upload photos and 3D virtual tours | |
| Manage viewing availability (Zap schedules) | |
| Confirm / cancel viewing reservations | |
| Generate & e-sign lease contracts | |
| Manage tenants | |
| Track expenses & profit/loss per property | |
| Purchase subscriptions & ad boosts | |
| Manage team members (agencies) | |
| View analytics dashboard | |
| Submit property reviews | (reserved for tenants) |
| Access `/home` customer feed | Redirect → `/owner/dashboard` |
| Access admin Filament panel | (requires Admin role) |

---

## Available Routes / Pages

| Route | Purpose |
|---|---|
| `/owner` | Landing redirect → dashboard or login |
| `/owner/login` | Owner email/password login |
| `/owner/register` | Owner registration (individual or agency) |
| `/owner/forgot-password` | Password reset |
| `/owner/auth` | OAuth callback handler |
| `/owner/dashboard` | **Main hub** — analytics, ad list, quick actions |
| `/owner/ads` | Full ad management (create / edit / delete) |
| `/owner/ads/[id]/edit` | Edit a specific listing |
| `/owner/availability` | Viewing schedule management |
| `/owner/lease-contracts` | Generate & manage lease contracts |
| `/owner/tenants` | Tenant CRM |
| `/owner/financials` | Expenses & P&L per property |
| `/owner/payments` | Payment settings & Flutterwave history |
| `/owner/viewings` | Incoming viewing reservations |
| `/owner/reviews` | Reviews received |
| `/owner/pro-services` | Pro service marketplace |
| `/owner/subscriptions` | Subscription plans & billing |
| `/owner/parametres` | Account & notification settings |
| `/owner/profile` | Owner profile management |
| `/owner/equipe` | Team members & invitations |
| `/owner/security` | Login history & device sessions |

---

## Navigation Flow

```
/owner/login or /owner/register
 Auth success → role cookie set → /owner/dashboard

/owner/dashboard
 Analytics cards (7d / 30d / 90d)
 Ad management table
 Create new ad → /owner/ads (wizard)
 Edit existing ad → /owner/ads/[id]/edit
 Toggle visibility → PATCH /ads/:id/toggle-visibility
 Boost ad → Payment flow
 Delete ad → DELETE /ads/:id
 Quick links → /owner/viewings, /owner/lease-contracts

/owner/ads (create)
 Multi-step wizard:
 1. Type & transaction type
 2. Location (Mapbox picker)
 3. Details & attributes
 4. Photos (WebP compression + upload)
 5. 3D Tour (optional, PSV upload)
 6. Pricing & publication

/owner/lease-contracts
 Select ad → Generate PDF → E-signature request

/owner/equipe
 Invite member → email invitation
 Revoke invitation / remove member
```

---

## Related Documentation

- [Authentication](./authentication.md)
- [Ad Management](./ad-management.md)
- [Virtual Tours](./virtual-tours.md)
- [Lease Contracts](./lease-contracts.md)
- [Payments](./payments.md)
- [Dashboard](./dashboard.md)
