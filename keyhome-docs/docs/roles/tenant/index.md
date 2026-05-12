---
sidebar_position: 1
title: "Tenant Role"
---

# Tenant (Customer) Role

## Description

A **Tenant** (also called *Customer* in the codebase, `UserRole.CUSTOMER`) is an authenticated user who actively uses KeyHome to find and secure rental or purchase properties. After registering and verifying their email, tenants gain access to personalised features: saved favourites, search alerts, booking viewings, submitting reviews, and receiving push notifications.

---

## Permissions

| Action | Allowed |
|---|---|
| All Visitor actions | |
| Save/manage favourites | |
| Book property viewings | |
| View contact info (unlock) | (requires credits) |
| Save search alerts | |
| Submit reviews | |
| Manage profile | |
| Receive push notifications | |
| Access payment history | |
| Compare properties | (up to 4 items) |
| Post listings | (Owner role required) |
| Access Owner dashboard | |

---

## Available Routes / Pages

| Route | Access | Purpose |
|---|---|---|
| `/login` | Public | Email/password + OAuth login |
| `/register` | Public | New account registration |
| `/forgot-password` | Public | Password reset request |
| `/reset-password` | Public | Password reset via token |
| `/verify-email` | Public | OTP email verification |
| `/verify-otp` | Public | OTP code entry |
| `/complete-profile` | Semi-auth | Post-OAuth profile setup |
| `/home` | Public/Auth | Main listings feed |
| `/nearby` | Public/Auth | Geo-based listings |
| `/profile` | **PRIVATE** | User profile management |
| `/my/reservations` | **PRIVATE** | Viewing booking history |
| `/notifications` | **PRIVATE** | Notification inbox |
| `/search-alerts` | **PRIVATE** | Saved search alerts |
| `/comparaisons` | **PRIVATE** | Property comparator |
| `/payments` | **PRIVATE** | Payment & credit history |
| `/parametres` | **PRIVATE** | Account settings |
| `/aide` | Auth | Help & support |
| `/contact` | Auth | Contact form |
| `/prix-marche` | Auth | Market price insights |

---

## Navigation Flow

```
Register/Login
 Email verification (OTP) → /verify-email → /verify-otp
 Complete profile → /complete-profile (OAuth only)
 /home (dashboard entry)

/home (Main Feed)
 Favourites → persisted via FavoritesProvider
 Search filters → /search?params
 Nearby → /nearby
 Ad click → /ads/[slug]

/ads/[slug] (Ad Detail)
 Book viewing → In-page slot picker
 Unlock contact → Payment flow (credits)
 Save to comparator → /comparaisons

/my/reservations
 Cancel reservation → API PATCH + UI update

/search-alerts
 Create/delete alert → API calls
```

---

## Related Documentation

- [Authentication](./authentication.md)
- [Profile Management](./profile.md)
- [Saved Searches & Alerts](./saved-searches.md)
- [Reviews](./reviews.md)
- [Viewing Scheduling](./viewing-scheduling.md)
