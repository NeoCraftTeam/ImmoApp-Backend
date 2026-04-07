---
sidebar_position: 4
title: "Saved Searches & Alerts"
---

# Saved Searches & Alerts

## Feature Description

Authenticated tenants can save their current search filters as a **Search Alert** to receive email notifications whenever a new listing matching their criteria becomes available. Saved alerts are managed at `/search-alerts` (PRIVATE route).

---

## User Journey

1. Authenticated user performs a search with filters (city, type, price, etc.)
2. Clicks "Sauvegarder cette recherche" (Save this search) button in the search bar or filter panel
3. A `POST /search-alerts` request is sent with the current filter state
4. The alert is saved and confirmed with a success toast
5. User navigates to `/search-alerts` to manage all saved alerts
6. User can:
 - View the list of all saved search alerts
 - Edit an alert's name or filters
 - Toggle email notification on/off for an alert
 - Delete an alert

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/(dashboard)/search-alerts/page.tsx` | Saved alerts management page |
| `src/components/dashboard/SearchAlertList.tsx` | List of saved alerts |
| `src/components/dashboard/SearchAlertCard.tsx` | Individual alert card |
| `src/components/ads/SaveSearchButton.tsx` | Button to save current search |
| `src/services/searchAlerts.service.ts` | `list()`, `create()`, `update()`, `remove()` |

---

## Key Code Snippets

### Search Alert Service

```typescript
// src/services/searchAlerts.service.ts
export const searchAlertsService = {
 async list(): Promise<{ data: SearchAlert[] }> {
 const { data } = await api.get('/search-alerts');
 return data;
 },

 async create(payload: SearchAlertPayload): Promise<{ data: SearchAlert }> {
 const { data } = await api.post('/search-alerts', payload);
 return data;
 },

 async update(id: string, payload: SearchAlertPayload): Promise<{ data: SearchAlert }> {
 const { data } = await api.put(`/search-alerts/${id}`, payload);
 return data;
 },

 async remove(id: string): Promise<void> {
 await api.delete(`/search-alerts/${id}`);
 },
};
```

### Search Alert Type

```typescript
interface SearchAlert {
 id: string;
 name?: string;
 q?: string;
 city?: string;
 type?: string;
 bedrooms?: number;
 price_min?: number;
 price_max?: number;
 surface_min?: number;
 surface_max?: number;
 has_parking?: boolean;
 email_notifications: boolean;
 created_at: string;
 last_match_at: string | null;
 matches_count: number;
}
```

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Body | Response |
|---|---|---|---|
| `GET` | `/api/v1/search-alerts` | — | `{ data: SearchAlert[] }` |
| `POST` | `/api/v1/search-alerts` | `SearchAlertPayload` | `{ data: SearchAlert }` |
| `PUT` | `/api/v1/search-alerts/:id` | `SearchAlertPayload` | `{ data: SearchAlert }` |
| `DELETE` | `/api/v1/search-alerts/:id` | — | `{}` |

---

## Alert Matching (Backend)

When a new listing is published with `status = available`, the backend dispatches a `MatchSearchAlertsForAdJob` to find all matching alerts and notify subscribers via email. This is handled entirely backend-side.

---

## Related Documentation

- [Ad Search](../visitor/ad-search.md)
