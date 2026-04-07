---
sidebar_position: 7
title: "Owner Dashboard"
---

# Owner Dashboard

## Feature Description

The **Owner Dashboard** (`/owner/dashboard`) is the central hub of the owner panel. It provides:

- **Analytics overview** — views, contacts, reservations over time
- **Ad management table** — status management, quick actions
- **Incoming viewing reservations** — confirm/cancel/annotate
- **Quick navigation** to all owner features

---

## Dashboard Layout

```
/owner/dashboard
 Welcome header (owner name, subscription status)
 Analytics Period Selector (7d / 30d / 90d)
 Stats Cards
 Total views (across all ads)
 Contact unlocks
 Viewing reservations
 Active listings count
 Charts
 Views over time (line chart — Recharts)
 Reservation funnel (bar chart)
 Ad Management Table
 Status filters (all / active / draft / archived)
 Search bar (by title)
 Bulk select & actions
 Per-ad actions (edit / toggle visibility / boost / delete)
 Recent Reservations Widget
```

---

## Analytics

```typescript
// src/services/owner.service.ts
const analytics = await ownerService.getAnalytics({ period: '30d' });
// GET /my/ads/analytics?period=30d

interface OwnerAnalyticsOverview {
 total_views: number;
 total_contacts: number;
 total_reservations: number;
 active_ads: number;
 views_over_time: Array<{ date: string; count: number }>;
 reservations_by_status: Record<string, number>;
}
```

---

## Viewing Reservations

Owners manage incoming viewing bookings from tenants:

```typescript
// src/services/owner.service.ts

// List all incoming reservations
const reservations = await ownerService.getViewingReservations({
 page: 1,
 status: 'pending', // optional filter
});

// Confirm a reservation
await ownerService.confirmReservation(reservationId);
// POST /reservations/:id/confirm

// Cancel a reservation
await ownerService.cancelReservation(reservationId, {
 cancellation_reason: 'Property no longer available'
});
// DELETE /reservations/:id

// Add private notes to a reservation
await ownerService.updateReservationNotes(reservationId, {
 landlord_notes: 'Client seems serious, call before visit'
});
// PATCH /reservations/:id/notes
```

### Viewing Reservation Type

```typescript
interface OwnerViewingReservation {
 id: string;
 status: ReservationStatus;
 slot_date: string;
 slot_starts_at: string;
 slot_ends_at: string;
 client_message: string | null;
 landlord_notes: string | null;
 cancellation_reason: string | null;
 ad: {
 id: string;
 title: string;
 slug: string;
 images: AdImage[];
 };
 client: {
 id: string;
 display_name: string;
 phone_number: string | null;
 avatar: string | null;
 };
 created_at: string;
}
```

---

## Financial Tracking

Owners can track expenses and profit/loss per property at `/owner/financials`:

```typescript
// Create an expense
await ownerService.createExpense(adId, {
 label: 'Réparation plomberie',
 amount: 35000,
 date: '2024-01-15',
 category: 'maintenance',
});
// POST /my/ads/:id/expenses

// Get profit/loss summary
const pnl = await ownerService.getProfitLoss(adId);
// GET /my/ads/:id/profit-loss
// Returns: { income: number, expenses: number, profit: number, expenses_by_category }
```

---

## Document Management

Owners can upload and manage documents (contracts, receipts, etc.) per listing:

```typescript
// Upload a document
const formData = new FormData();
formData.append('file', file);
formData.append('type', 'contract');
await ownerService.uploadDocument(adId, formData);
// POST /my/ads/:id/documents

// Download a document
const blob = await ownerService.downloadDocument(documentId);
// GET /my/documents/:id/download
```

---

## Team Management

Agencies can manage team members at `/owner/equipe`:

```typescript
// Invite a team member
await ownerService.inviteTeamMember({ email: 'agent@agency.cm', role: 'agent' });
// POST /my/team/invite

// Accept invitation (from email link)
await ownerService.acceptTeamInvitation(invitationToken);
// POST /my/team/invitations/:token/accept

// Revoke invitation
await ownerService.revokeTeamInvitation(invitationId);
// DELETE /my/team/invitations/:id

// Remove team member
await ownerService.removeTeamMember(memberId);
// DELETE /my/team/members/:id
```

---

## Security & Login History

At `/owner/security`, owners can review their login history and manage active sessions:

```typescript
// Get login history
const history = await ownerService.getLoginHistory({ page: 1 });
// GET /my/login-history → { data: LoginHistoryEntry[], meta }

interface LoginHistoryEntry {
 id: string;
 ip_address: string;
 user_agent: string;
 country: string | null;
 city: string | null;
 success: boolean;
 created_at: string;
}

// Clear all history
await ownerService.clearLoginHistory();
// DELETE /my/login-history
```

---

## Notification Preferences

Owners configure notification settings at `/owner/parametres`:

```typescript
// Get preferences
const prefs = await ownerService.getNotificationPreferences();
// GET /my/notification-preferences

// Update preferences
await ownerService.updateNotificationPreferences({
 email_new_reservation: true,
 email_reservation_cancelled: true,
 email_new_review: true,
 push_new_reservation: false,
});
// PUT /my/notification-preferences
```

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/(owner)/owner/dashboard/page.tsx` | Main dashboard page |
| `src/app/(owner)/owner/viewings/page.tsx` | Viewing reservations |
| `src/app/(owner)/owner/financials/page.tsx` | Expenses & P&L |
| `src/app/(owner)/owner/equipe/page.tsx` | Team management |
| `src/app/(owner)/owner/security/page.tsx` | Login history |
| `src/app/(owner)/owner/reviews/page.tsx` | Received reviews |
| `src/components/owner/OwnerLayoutClient.tsx` | Owner panel shell |
| `src/components/owner/OwnerDashboard.tsx` | Dashboard composition |
| `src/components/owner/AnalyticsCards.tsx` | Stats overview cards |
| `src/components/owner/ViewsChart.tsx` | Recharts line chart |
| `src/components/owner/ReservationTable.tsx` | Incoming reservations table |
| `src/components/owner/FinancialSummary.tsx` | Expense & P&L display |
| `src/services/owner.service.ts` | All owner API calls |
| `src/lib/owner-dashboard-analytics.ts` | Analytics data formatters |

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Params / Body | Response |
|---|---|---|---|
| `GET` | `/api/v1/my/ads/analytics` | `{ period: '7d' \| '30d' \| '90d' }` | `OwnerAnalyticsOverview` |
| `GET` | `/api/v1/my/viewing-reservations` | `{ page?, status? }` | `{ data: OwnerViewingReservation[], meta }` |
| `POST` | `/api/v1/reservations/:id/confirm` | — | `{}` |
| `DELETE` | `/api/v1/reservations/:id` | `{ cancellation_reason? }` | `{}` |
| `PATCH` | `/api/v1/reservations/:id/notes` | `{ landlord_notes }` | `{}` |
| `GET` | `/api/v1/my/ads/:id/expenses` | `{ page? }` | `{ data: Expense[], meta }` |
| `POST` | `/api/v1/my/ads/:id/expenses` | `ExpensePayload` | `{ data: Expense }` |
| `DELETE` | `/api/v1/my/expenses/:id` | — | `{}` |
| `GET` | `/api/v1/my/ads/:id/profit-loss` | — | `ProfitLoss` |
| `GET` | `/api/v1/my/ads/:id/documents` | `{ type? }` | `{ data: OwnerDocument[] }` |
| `POST` | `/api/v1/my/ads/:id/documents` | `FormData` | `{ data: OwnerDocument }` |
| `GET` | `/api/v1/my/documents/:id/download` | — | `Blob` |
| `DELETE` | `/api/v1/my/documents/:id` | — | `{}` |
| `GET` | `/api/v1/my/reviews` | `{ page?, per_page? }` | Paginated reviews |
| `GET` | `/api/v1/my/team` | — | `{ members, invitations }` |
| `POST` | `/api/v1/my/team/invite` | `{ email, role }` | `TeamInvitation` |
| `DELETE` | `/api/v1/my/team/invitations/:id` | — | `{}` |
| `DELETE` | `/api/v1/my/team/members/:id` | — | `{}` |
| `GET` | `/api/v1/my/login-history` | `{ page? }` | `{ data: LoginHistoryEntry[], meta }` |
| `DELETE` | `/api/v1/my/login-history` | — | `{}` |
| `GET` | `/api/v1/my/notification-preferences` | — | `NotificationPreferences` |
| `PUT` | `/api/v1/my/notification-preferences` | Preferences object | `NotificationPreferences` |

---

## Related Documentation

- [Ad Management](./ad-management.md)
- [Lease Contracts](./lease-contracts.md)
- [Payments](./payments.md)
