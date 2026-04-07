---
sidebar_position: 1
title: "Admin Role"
---

# Admin Role

## Description

The **Admin** role provides full platform management capabilities. The primary admin interface is the **Laravel Filament admin panel** hosted at `/admin` on the backend. The Next.js frontend interacts with the admin panel via an **SSO (Single Sign-On) redirect** mechanism rather than maintaining a separate admin UI.

Admins can access the Filament panel directly from the Next.js frontend via a seamless SSO redirect — no separate login is required once authenticated via the API.

---

## Permissions

Admins have unrestricted access to all platform resources through the Filament panel:

| Resource | Operations |
|---|---|
| Users | View, edit, ban, manage roles |
| Ads / Listings | View, approve, decline, delete all ads |
| Agencies | Create, edit, delete agencies |
| Payments | View all transactions, issue refunds |
| Subscriptions | Manage plans and user subscriptions |
| Reviews | Moderate and delete reviews |
| Ad Reports | Review and action abuse reports |
| Surveys | Create and manage platform surveys |
| Point Packages | Configure credit package pricing |
| Promo Codes | Create and manage promo codes |
| Newsletter | Send campaigns |
| Site Settings | Global platform configuration |
| Activity Logs | Audit trail of all actions |

---

## Panel SSO Flow

The Filament admin panel uses session-based auth with TOTP MFA. From the Next.js frontend, the `panel_sso_url` returned at login enables seamless redirect without re-authentication.

See [Panel SSO documentation](./panel-sso.md) for full technical details.

---

## Available Routes (Frontend)

There are **no dedicated admin pages** in the Next.js frontend. Admins use:

| Access Point | Description |
|---|---|
| `/admin` (backend) | Full Filament admin panel |
| `panel_sso_url` | Auto-login URL returned by `POST /auth/login` for admin accounts |

---

## Related Documentation

- [Panel SSO](./panel-sso.md)
