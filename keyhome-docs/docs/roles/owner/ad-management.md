---
sidebar_position: 3
title: "Ad Management"
---

# Ad Management

## Feature Description

The **Ad Management** section (`/owner/ads`) is the core of the owner panel, allowing landlords and agents to create, edit, publish, boost, and delete property listings. The creation wizard is a multi-step form with photo upload, optional 3D tour upload, location picking (Mapbox), and AI-powered description enhancement.

---

## User Journey

### Creating a New Listing

1. Owner clicks "Nouvelle annonce" from the dashboard or `/owner/ads`
2. Multi-step wizard opens:

 **Step 1 — Transaction & Property Type**
 - Select: Rent / Sale / Short-term rental
 - Property type: Apartment, House, Office, Land, etc.

 **Step 2 — Location**
 - City selection (autocomplete)
 - Quarter/Neighbourhood (optional)
 - Interactive Mapbox map to pin exact location
 - Address fields

 **Step 3 — Property Details**
 - Title (auto-generated from type + location)
 - Description (text + AI enhancement button)
 - Surface area (m²)
 - Number of bedrooms, bathrooms
 - Floor/level
 - Property attributes (checkboxes): WiFi, AC, furnished, garden, pool, etc.

 **Step 4 — Photos**
 - Drag-and-drop or browse file upload
 - Images compressed client-side (WebP optimisation via `lib/image-compression.ts`)
 - Reorder images, set primary photo

 **Step 5 — 3D Virtual Tour** (optional)
 - Upload panoramic JPG images for each scene
 - Scene upload timeout: 600 seconds (large files)
 - After upload, hotspots are configured in the tour editor

 **Step 6 — Pricing & Publication**
 - Set price (XOF/XAF)
 - Monthly charges (electricity, water, internet, other)
 - Choose to save as draft or publish immediately

3. On form submit → `POST /ads` (FormData, 120s timeout)
4. On success → redirect to ad detail in owner panel

### Editing a Listing

1. Owner selects an existing listing
2. Same wizard pre-filled with existing data
3. Saves → `POST /ads/:id` with `_method: 'PUT'` (Laravel method spoofing)

### Publishing / Status Management

Owners can change listing status via the status dropdown:

| Status | Description |
|---|---|
| `draft` | Not visible to the public |
| `available` | Published and searchable |
| `reserved` | Marked as reserved (still visible) |
| `rent` | Currently rented |
| `sold` | Property sold |

```typescript
// Owner changes status
await adsService.setStatus(adId, 'available');
// POST /ads/:id/set-status { status: 'available' }
```

### Bulk Operations

From the ad management table, owners can:
- **Bulk update status**: Select multiple ads → change status
- **Bulk delete**: Select multiple ads → delete

```typescript
await ownerService.bulkUpdateAdStatus({ ids: ['uuid1', 'uuid2'], status: 'draft' });
await ownerService.bulkDeleteAds({ ids: ['uuid1', 'uuid2'] });
```

### Visibility Toggle

Toggle an ad's public visibility without changing its status:

```typescript
await adsService.toggleVisibility(adId);
// POST /ads/:id/toggle-visibility → { is_visible: boolean }
```

### Duplicate an Ad

Quick duplicate from the management table:

```typescript
const { id, slug } = await ownerService.duplicateAd(adId);
// POST /my/ads/:id/duplicate → { id, slug }
```

### Boost an Ad

See [Payments documentation](./payments.md) for the full boost flow.

---

## Availability Schedules (Zap)

Owners define **when they accept viewing requests** per property. This uses the Zap scheduling engine.

1. Owner navigates to `/owner/availability` or the availability tab of a listing
2. Creates a recurring schedule: day of week, start time, end time, duration per slot
3. Schedule is saved via `POST /ads/:id/availability`
4. Tenants see available slots when booking a viewing

```typescript
// Create availability schedule
await ownerService.createAvailability(adId, {
 day_of_week: 1, // Monday (0=Sunday)
 starts_at: '09:00',
 ends_at: '17:00',
 slot_duration: 60, // Minutes per slot
 max_bookings: 1, // Bookings per slot
});
```

---

## AI Description Enhancement

Owners can use AI to enhance their property description:

```typescript
// src/services/ads.service.ts
const { enhanced } = await adsService.enhanceDescription({
 description: 'Grand appartement meublé avec vue.'
});
// POST /ads/ai/enhance-description
// Returns: { enhanced: 'Grand appartement meublé offrant une vue panoramique...' }
```

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/(owner)/owner/ads/page.tsx` | Ad management table |
| `src/app/(owner)/owner/ads/[id]/edit/page.tsx` | Edit specific ad |
| `src/components/owner/AdWizard.tsx` | Multi-step ad creation/edit wizard |
| `src/components/owner/AdWizardStep1.tsx` | Transaction & type step |
| `src/components/owner/AdWizardStep2.tsx` | Location step (Mapbox) |
| `src/components/owner/AdWizardStep3.tsx` | Details & attributes step |
| `src/components/owner/AdWizardStep4.tsx` | Photo upload step |
| `src/components/owner/AdWizardStep5.tsx` | 3D tour step |
| `src/components/owner/AdWizardStep6.tsx` | Pricing & publication step |
| `src/components/owner/AdManagementTable.tsx` | Owner's ad table with bulk actions |
| `src/components/owner/AvailabilityManager.tsx` | Viewing schedule management |
| `src/services/ads.service.ts` | `create()`, `update()`, `destroy()`, `toggleVisibility()`, `setStatus()`, `enhanceDescription()` |
| `src/services/owner.service.ts` | `getMyAds()`, `duplicateAd()`, `bulkUpdateAdStatus()`, `bulkDeleteAds()`, `createAvailability()`, etc. |
| `src/lib/image-compression.ts` | Client-side image compression |
| `src/lib/attribute-labels.ts` | Property attribute display labels |
| `src/hooks/useAutoSave.ts` | Draft auto-save (2s debounce) |

---

## Auto-Save (Draft Persistence)

The wizard uses `useAutoSave` to persist draft data to `localStorage` every 2 seconds:

```typescript
// src/hooks/useAutoSave.ts
const { savedAt, hasDraft, restoreDraft, clearDraft } = useAutoSave({
 key: 'ad-wizard-draft',
 data: formValues,
 enabled: true,
});
```

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Body | Response |
|---|---|---|---|
| `GET` | `/api/v1/my/ads` | `{ page?, status?, q?, city_id? }` | `PaginatedResponse<Ad>` |
| `POST` | `/api/v1/ads` | `FormData` (multipart, 120s timeout) | `{ data: Ad }` |
| `POST` | `/api/v1/ads/:id` | `FormData + _method=PUT` (120s timeout) | `{ data: Ad }` |
| `DELETE` | `/api/v1/ads/:id` | — | `{}` |
| `POST` | `/api/v1/ads/:id/toggle-visibility` | — | `{ is_visible: boolean }` |
| `POST` | `/api/v1/ads/:id/set-status` | `{ status }` | `{ old_status, new_status }` |
| `POST` | `/api/v1/my/ads/:id/duplicate` | — | `{ id, slug }` |
| `PUT` | `/api/v1/my/ads/bulk-update` | `{ ids, status }` | `{ updated, failed }` |
| `POST` | `/api/v1/my/ads/bulk-delete` | `{ ids }` | `{ deleted }` |
| `POST` | `/api/v1/ads/ai/enhance-description` | `{ description }` | `{ enhanced }` |
| `GET` | `/api/v1/ads/:id/availability` | — | `AvailabilitySchedule[]` |
| `POST` | `/api/v1/ads/:id/availability` | `AvailabilityPayload` | — |
| `PUT` | `/api/v1/ads/:id/availability/:scheduleId` | Partial payload | — |
| `DELETE` | `/api/v1/ads/:id/availability/:scheduleId` | — | — |

---

## Related Documentation

- [Virtual Tours](./virtual-tours.md)
- [Payments & Boost](./payments.md)
- [Owner Dashboard](./dashboard.md)
