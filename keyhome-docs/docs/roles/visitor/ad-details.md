---
sidebar_position: 4
title: "Ad Details"
---

# Ad Details

## Feature Description

The **Ad Detail Page** is the full property listing page, accessible to all users (authenticated or not). It displays comprehensive property information including photos, a 3D virtual tour (if available), location on a map, neighbourhood scorecard, pricing, owner contact details (behind an unlock paywall), and booking options.

---

## User Journey

1. User clicks a property card from search results or the landing page
2. Browser navigates to `/ads/[slug]` (or `/ads/[uuid]`)
3. Page fetches the ad details via `GET /ads/:id`
4. Ad view is tracked (fire-and-forget `POST /ads/:id/view`)
5. User browses:
 - **Photo gallery** — swipeable image carousel with lightbox
 - **Property details** — surface, bedrooms, bathrooms, attributes
 - **Location** — Mapbox map with pin
 - **Neighbourhood scorecard** — POI scores (schools, transport, amenities)
 - **3D virtual tour** (if `has_3d_tour = true`) — Photo Sphere Viewer
 - **Price** — formatted in XOF/XAF
 - **Charges/fees** — utility charges if applicable
6. **Contact Owner** button:
 - If `is_unlocked = true` → shows phone/email directly
 - If `is_unlocked = false` → triggers credits unlock payment flow
 - If unauthenticated → redirects to `/login`
7. **Book a Viewing** button:
 - If unauthenticated → redirect to `/login`
 - If authenticated → opens inline slot picker (date + time selection)
8. User can toggle favourite (authenticated users only)
9. User can add to comparator
10. User can see related listings (same city/type)
11. User can submit a review (authenticated tenants only)

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/(dashboard)/ads/[id]/page.tsx` | Ad detail route page |
| `src/components/ads/AdDetail.tsx` | Main detail composition component |
| `src/components/ads/AdImages.tsx` | Image carousel & lightbox gallery |
| `src/components/ads/AdTour.tsx` | 3D tour viewer (Photo Sphere Viewer) |
| `src/components/ads/AdMap.tsx` | Mapbox location map |
| `src/components/ads/AdAttributes.tsx` | Property attribute chips |
| `src/components/ads/AdContact.tsx` | Contact/unlock section |
| `src/components/ads/AdBooking.tsx` | Viewing slot picker |
| `src/components/ads/NeighborhoodScorecard.tsx` | POI scorecard display |
| `src/components/ads/AdReviews.tsx` | Reviews list & submission form |
| `src/components/ads/RelatedAds.tsx` | Similar listings section |
| `src/components/viewing/SlotPicker.tsx` | Date + time slot selection |
| `src/services/ads.service.ts` | `show()`, `trackView()`, `getNeighborhoodScorecard()` |
| `src/services/viewings.service.ts` | `getSlots()`, `reserve()` |
| `src/services/reviews.service.ts` | `create()` |
| `src/providers/FavoritesProvider.tsx` | Favourite toggle |
| `src/providers/ComparatorProvider.tsx` | Add to comparator |
| `src/lib/inferEquirectangularPanoData.ts` | PSV panorama metadata |
| `src/lib/psvPitchClampForPartialEquirect.ts` | Partial panorama pitch clamp |

---

## Ad Data Model

```typescript
interface Ad {
 id: string;
 title: string;
 slug: string;
 description: string;
 price: number;
 surface_area: number;
 bedrooms: number;
 bathrooms: number;
 has_parking: boolean;
 location: { lat: number; lng: number };
 status: AdStatus; // available | reserved | rent | pending | draft | sold | declined
 is_unlocked: boolean; // Whether current user has unlocked contact info
 has_3d_tour: boolean;
 tour_config: TourConfig | null;
 rating: number | null;
 reviews_count: number;
 images: AdImage[];
 user: User;
 agency: Agency | null;
 attributes: PropertyAttribute[];
 // Charges
 electricity_charges?: number;
 water_charges?: number;
 internet_charges?: number;
 other_charges?: number;
}
```

---

## Key Code Snippets

### Fetch Ad Detail

```typescript
// src/services/ads.service.ts
async show(id: string): Promise<Ad> {
 const { data } = await api.get(`/ads/${id}`);
 return data.data;
},
```

### Track Ad View (fire & forget)

```typescript
// src/services/ads.service.ts
async trackView(id: string): Promise<void> {
 api.post(`/ads/${id}/view`).catch(() => {}); // Silently ignore errors
},
```

### Unlock Contact Info

```typescript
// Triggered when user clicks "Voir le contact"
// src/services/payments.service.ts
async initialize(adId: string): Promise<UnlockResponse> {
 const { data } = await api.post(`/payments/initialize/${adId}`);
 return data;
},
```

If the response is `{ status: 'insufficient_points' }`, the payment flow is triggered to purchase credits.

### 3D Tour Integration

```typescript
// src/components/ads/AdTour.tsx
// Uses Photo Sphere Viewer (PSV) with custom panorama inference
import { Viewer } from '@photo-sphere-viewer/core';
import { MarkersPlugin } from '@photo-sphere-viewer/markers-plugin';
import { inferEquirectangularPanoData } from '@/lib/inferEquirectangularPanoData';

// Each TourScene has:
interface TourScene {
 id: string;
 title: string;
 image_url: string;
 initial_view: { pitch: number; yaw: number; zoom: number };
 hotspots: TourHotspot[]; // Navigation points between scenes
 haov?: number; // Horizontal angle of view
 vaov?: number; // Vertical angle of view
 vOffset?: number; // Vertical offset
 is_partial_pano: boolean; // Whether it's a partial panorama
}
```

### Neighbourhood Scorecard Fetch

```typescript
// src/services/ads.service.ts
async getNeighborhoodScorecard(id: string, force?: boolean) {
 const { data } = await api.get(`/ads/${id}/neighborhood-scorecard`, {
 params: { force }
 });
 return data;
},
```

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Params / Body | Response |
|---|---|---|---|
| `GET` | `/api/v1/ads/:id` | — | `{ data: Ad }` |
| `POST` | `/api/v1/ads/:id/view` | — | `{}` (fire & forget) |
| `GET` | `/api/v1/ads/:id/neighborhood-scorecard` | `force?: boolean` | `NeighborhoodScorecard` |
| `POST` | `/api/v1/payments/initialize/:adId` | — | `UnlockResponse` |

### Unlock Response Shape

```json
{
 "status": "unlocked" | "insufficient_points" | "owner" | "already_unlocked",
 "packages": [
 {
 "id": "uuid",
 "name": "Pack Starter",
 "price": 2000,
 "points_awarded": 500,
 "is_popular": false
 }
 ],
 "points": 250
}
```

### Neighbourhood Scorecard Shape

```json
{
 "overall_score": 78,
 "categories": {
 "schools": { "score": 85, "pois": [] },
 "transport": { "score": 70, "pois": [] },
 "health": { "score": 80, "pois": [] },
 "commerce": { "score": 75, "pois": [] }
 }
}
```

---

## UUID vs Slug Lookup

The backend handles both UUID and slug-based lookups for the ad detail endpoint. The frontend may pass either a UUID or a slug as the `id` URL parameter:

```
/ads/appartement-3-pieces-bastos ← slug
/ads/550e8400-e29b-41d4-a716-446655440000 ← UUID
```

See backend AGENTS.md for the UUID guard pattern used in `AdController::show()`.

---

## SEO

- The ad detail page uses `generateMetadata()` with:
 - `title`: Ad title + city
 - `description`: First 160 characters of ad description
 - `og:image`: Primary ad image
 - **JSON-LD**: `RealEstateListing` structured data
