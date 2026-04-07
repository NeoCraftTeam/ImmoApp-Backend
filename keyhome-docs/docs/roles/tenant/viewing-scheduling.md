---
sidebar_position: 6
title: "Viewing Scheduling"
---

# Viewing Scheduling

## Feature Description

Authenticated tenants can **book property viewings** directly from the ad detail page. The viewing scheduling system is powered by **Zap** (the `laraveljutsu/zap` scheduling engine), which allows property owners to define recurring availability windows. Tenants can then see available time slots and book a specific date/time.

---

## User Journey

1. Authenticated tenant views an ad detail page (`/ads/[slug]`)
2. Clicks "Réserver une visite" (Book a viewing)
3. A date picker appears (calendar view)
4. Tenant selects a date → available time slots for that date load
5. Time slots are colour-coded: green (available), grey (taken/unavailable)
6. Tenant selects a slot, optionally adds a message to the owner
7. Clicks "Confirmer la réservation"
8. `POST /ads/:adId/reservations` is called
9. On success:
 - Tenant receives a confirmation email
 - Owner is notified of the new booking
 - Reservation appears in `/my/reservations`
10. Tenant can cancel the reservation from `/my/reservations`

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/(dashboard)/my/reservations/page.tsx` | Tenant's booking history |
| `src/components/ads/AdBooking.tsx` | Booking button + slot picker trigger |
| `src/components/viewing/SlotPicker.tsx` | Calendar + time slot grid |
| `src/components/viewing/ReservationCard.tsx` | Individual reservation display |
| `src/services/viewings.service.ts` | `getSlots()`, `reserve()`, `myReservations()`, `cancel()` |
| `src/types/viewing.ts` | `BookableSlot`, `Reservation`, `ReservationStatus` |
| `src/hooks/useViewingResponseSync.ts` | Cross-tab reservation state sync |

---

## Key Code Snippets

### Fetch Available Slots

```typescript
// src/services/viewings.service.ts
async getSlots(adId: string, date: string): Promise<BookableSlot[]> {
 const { data } = await api.get(`/ads/${adId}/slots`, { params: { date } });
 return data.data;
},
```

### BookableSlot Type

```typescript
interface BookableSlot {
 starts_at: string; // e.g. "09:00"
 ends_at: string; // e.g. "10:00"
 is_available: boolean;
}
```

### Create Reservation

```typescript
// src/services/viewings.service.ts
interface CreateReservationPayload {
 slot_date: string; // "YYYY-MM-DD"
 slot_starts_at: string; // "HH:MM"
 slot_ends_at: string; // "HH:MM"
 client_message?: string; // Optional message to owner
}

async reserve(adId: string, payload: CreateReservationPayload): Promise<Reservation> {
 const { data } = await api.post(`/ads/${adId}/reservations`, payload);
 return data.data;
},
```

### Cancel Reservation

```typescript
// src/services/viewings.service.ts
async cancel(reservationId: string, reason?: string): Promise<Reservation> {
 const { data } = await api.delete(`/reservations/${reservationId}`, {
 data: { cancellation_reason: reason }
 });
 return data.data;
},
```

### Reservation Status

```typescript
enum ReservationStatus {
 Pending = 'pending',
 Confirmed = 'confirmed',
 Cancelled = 'cancelled',
 Expired = 'expired',
}

interface Reservation {
 id: string;
 status: ReservationStatus;
 slot_date: string;
 slot_starts_at: string;
 slot_ends_at: string;
 client_message: string | null;
 landlord_notes: string | null;
 cancellation_reason: string | null;
 ad: Ad;
 client: User;
 created_at: string;
}
```

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Params / Body | Response |
|---|---|---|---|
| `GET` | `/api/v1/ads/:adId/slots` | `{ date: string }` | `{ data: BookableSlot[] }` |
| `POST` | `/api/v1/ads/:adId/reservations` | `CreateReservationPayload` | `{ data: Reservation }` |
| `GET` | `/api/v1/my/reservations` | `{ ad_id?, status? }` | `{ data: Reservation[] }` |
| `DELETE` | `/api/v1/reservations/:id` | `{ cancellation_reason? }` | `{ data: Reservation }` |

---

## Owner Side

For the owner's perspective on managing viewings, see:
- [Owner Viewing Management](../owner/dashboard.md#viewing-reservations)
- [Owner Availability Setup](../owner/ad-management.md#availability-schedules)

---

## Related Documentation

- [Ad Details](../visitor/ad-details.md)
- [Owner Dashboard](../owner/dashboard.md)
