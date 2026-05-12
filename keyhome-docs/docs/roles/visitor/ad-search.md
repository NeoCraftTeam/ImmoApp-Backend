---
sidebar_position: 3
title: "Ad Search"
---

# Ad Search

## Feature Description

The **Ad Search** feature allows any user (authenticated or not) to browse, filter, and sort the full catalogue of property listings. It combines a real-time text search (Meilisearch-powered on the backend) with facet-based filtering (city, property type, price range, surface area, bedrooms, parking, etc.) and multiple sort options.

The search experience is available on two pages:
- `/home` — The main listings feed with a sticky search bar
- `/search` — A full-screen advanced search view

Both use the same underlying `adsService.search()` function and support URL-synced filter state, enabling shareable search links and browser history navigation.

---

## User Journey

1. Visitor arrives at `/home` or `/search`
2. The default listing feed loads (paginated, most recent first)
3. Visitor types in the search bar → results update in real time (debounced)
4. Visitor applies filters (city, type, price range, bedrooms, etc.)
5. Results update immediately; pagination resets to page 1
6. Visitor clicks on a property card → navigated to `/ads/[slug]`
7. Visitor can save the search as an alert (authentication required)
8. Visitor can add listings to comparison (up to 4 items)
9. Visitor can toggle favourites (authentication required to persist)

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/(dashboard)/home/page.tsx` | Main feed page |
| `src/app/(dashboard)/search/page.tsx` | Advanced search page |
| `src/app/(dashboard)/nearby/page.tsx` | Geolocation-based listings |
| `src/components/ads/AdCard.tsx` | Individual listing card |
| `src/components/ads/AdList.tsx` | Listing grid/list with pagination |
| `src/components/ads/SearchBar.tsx` | Text search input with autocomplete |
| `src/components/ads/FilterDrawer.tsx` | Mobile filter panel |
| `src/components/ads/FilterBar.tsx` | Desktop filter chips/sidebar |
| `src/components/ads/SortMenu.tsx` | Sort order selector |
| `src/components/ads/ComparatorBar.tsx` | Floating comparison toolbar |
| `src/components/ads/FacetSection.tsx` | Facet-based filter controls |
| `src/services/ads.service.ts` | `search()`, `nearby()`, `facets()`, `autocomplete()` |
| `src/services/searchAlerts.service.ts` | Save/manage search alerts |
| `src/providers/FavoritesProvider.tsx` | Favourite toggle state |
| `src/providers/ComparatorProvider.tsx` | Comparison cart state |
| `src/hooks/useUserLocation.ts` | Geolocation for nearby search |
| `src/hooks/useSearchHistory.ts` | Recent search persistence |
| `src/types/index.ts` | `SearchParams`, `Ad`, `FacetsResponse` |

---

## Search Parameters

```typescript
interface SearchParams {
 q?: string; // Full-text query
 city?: string; // City slug or ID
 type?: string; // Property type ID
 bedrooms?: number; // Number of bedrooms
 price_min?: number; // Minimum price (XOF)
 price_max?: number; // Maximum price (XOF)
 surface_min?: number; // Minimum surface area (m²)
 surface_max?: number; // Maximum surface area (m²)
 has_parking?: boolean; // Parking filter
 sort?: string; // Sort field (price_asc, price_desc, recent, etc.)
 page?: number; // Pagination page
 per_page?: number; // Results per page
 transaction_type?: string; // rent | sale
 quarter_id?: string; // Neighbourhood/quarter filter
 quarter_name?: string; // Display name for quarter
}
```

---

## Key Code Snippets

### Search Service Call

```typescript
// src/services/ads.service.ts
export const adsService = {
 async search(params: SearchParams): Promise<PaginatedResponse<Ad>> {
 const { data } = await api.get('/ads/search', { params });
 return data;
 },

 async facets(): Promise<FacetsResponse> {
 const { data } = await api.get('/ads/facets');
 return data;
 },

 async autocomplete(field: string, q: string): Promise<AutocompleteResult[]> {
 const { data } = await api.get('/ads/autocomplete', { params: { field, q } });
 return data;
 },
};
```

### Nearby Search

```typescript
// src/services/ads.service.ts
async nearby(params: NearbyParams): Promise<Ad[]> {
 const { data } = await api.get('/ads/nearby', { params });
 return data;
},
```

### Geolocation Hook

```typescript
// src/hooks/useUserLocation.ts
export function useUserLocation() {
 const [location, setLocation] = useState<GeolocationCoords | null>(null);
 const [loading, setLoading] = useState(false);

 // 10-minute cache, 5km accuracy threshold, watchPosition support
 const refresh = useCallback(() => {
 setLoading(true);
 navigator.geolocation.getCurrentPosition(
 (pos) => {
 setLocation({ lat: pos.coords.latitude, lng: pos.coords.longitude });
 setLoading(false);
 },
 () => setLoading(false),
 { enableHighAccuracy: true, timeout: 10000 }
 );
 }, []);

 return { location, loading, refresh };
}
```

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Query Params | Response |
|---|---|---|---|
| `GET` | `/api/v1/ads/search` | `SearchParams` | `PaginatedResponse<Ad>` |
| `GET` | `/api/v1/ads` | `page, per_page, order_by, type` | `PaginatedResponse<Ad>` |
| `GET` | `/api/v1/ads/nearby` | `latitude, longitude, radius?` | `Ad[]` |
| `GET` | `/api/v1/ads/facets` | — | `FacetsResponse` |
| `GET` | `/api/v1/ads/autocomplete` | `field, q` | `AutocompleteResult[]` |

### Facets Response Shape

```json
{
 "cities": [{ "value": "yaounde", "count": 1240 }],
 "types": [{ "value": "apartment", "count": 860 }],
 "bedrooms": [{ "value": 2, "count": 400 }],
 "price_range": { "min": 50000, "max": 5000000 },
 "surface_range": { "min": 15, "max": 800 },
 "has_parking": { "true": 320, "false": 920 }
}
```

### Paginated Ad Response Shape

```json
{
 "data": [
 {
 "id": "uuid",
 "title": "Appartement 3 pièces Bastos",
 "slug": "appartement-3-pieces-bastos",
 "price": 250000,
 "surface_area": 90,
 "bedrooms": 3,
 "location": { "lat": 3.8667, "lng": 11.5167 },
 "status": "available",
 "is_unlocked": false,
 "images": [{ "url": "...", "thumb": "...", "is_primary": true }],
 "user": { "id": "uuid", "display_name": "Jean D." },
 "agency": null
 }
 ],
 "meta": {
 "current_page": 1,
 "last_page": 12,
 "per_page": 20,
 "total": 240
 }
}
```

---

## Comparator Feature

Visitors can add up to **4 listings** to a side-by-side comparator:

```typescript
// src/providers/ComparatorProvider.tsx
interface ComparatorContext {
 items: Ad[]; // Currently selected items (max 4)
 add(ad: Ad): void;
 remove(id: string): void;
 isSelected(id: string): boolean;
 maxReached: boolean; // True when 4 items selected
 openDrawer(mode: 'table' | 'recently_viewed'): void;
}
```

The comparator state is persisted to `localStorage` under the key `keyhome_comparator`.

---

## Search Alert (Save Search)

Authenticated users can save their current search filters as an alert to receive email notifications for new matching listings:

```typescript
// src/services/searchAlerts.service.ts
await searchAlertsService.create({
 q: 'appartement bastos',
 city: 'yaounde',
 type: 'apartment',
 price_max: 300000,
 bedrooms: 2,
});
```

See [Saved Searches & Alerts](../tenant/saved-searches.md) for full documentation.
