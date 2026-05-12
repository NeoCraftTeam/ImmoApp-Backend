---
sidebar_position: 4
title: "Virtual Tours"
---

# Virtual Tours (3D)

## Feature Description

Owners can create immersive **360° virtual property tours** by uploading panoramic images for each room (scene). The tour engine is powered by **Photo Sphere Viewer (PSV)** and rendered directly in the browser — no plugins required.

Virtual tours are uploaded per-listing and can include interactive **hotspots** that allow viewers to navigate between rooms.

---

## User Journey

### Owner: Creating a Virtual Tour

1. Owner reaches Step 5 of the Ad Wizard or navigates to the tour editor for an existing listing
2. Uploads panoramic JPEG/PNG images (one per scene/room)
 - Each image should be an equirectangular panorama (360° × 180°)
 - Partial panoramas (partial horizontal/vertical angle) are also supported
3. Upload is sent as `FormData` to `POST /ads/:id/tour/scenes` (600-second timeout for large files)
4. Backend processes the scenes asynchronously (`ProcessTourSceneJob`)
5. Once processed, scenes appear in the tour editor
6. Owner can:
 - **Rename** each scene (room name)
 - **Set initial view** (pitch, yaw, zoom for opening perspective)
 - **Add hotspots** — clickable markers that navigate to another scene
7. Hotspot changes are saved via `PATCH /ads/:id/tour/scenes/:sceneId/hotspots`
8. Tour is automatically published when the listing is published

### Tenant/Visitor: Viewing a Virtual Tour

1. On the ad detail page, if `ad.has_3d_tour === true`, a "Voir le tour 3D" button appears
2. Click opens the PSV viewer (inline or full-screen modal)
3. User can:
 - Look around 360° (drag/gyroscope on mobile)
 - Click hotspots to jump to another scene (room)
 - Use keyboard shortcuts (WASD / arrow keys)
 - Toggle full-screen
 - Zoom in/out

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/components/ads/AdTour.tsx` | PSV viewer component for ad detail page |
| `src/components/owner/TourEditor.tsx` | Tour scene management & hotspot editor |
| `src/components/owner/TourSceneUpload.tsx` | Panorama image upload dropzone |
| `src/components/owner/HotspotEditor.tsx` | Visual hotspot placement on panorama |
| `src/services/ads.service.ts` | `getTour()`, `uploadTourScenes()`, `updateHotspots()`, `deleteTour()` |
| `src/lib/inferEquirectangularPanoData.ts` | Auto-detect panorama dimensions from image metadata |
| `src/lib/psvKeyboardActions.ts` | PSV keyboard navigation plugin config |
| `src/lib/psvPitchClampForPartialEquirect.ts` | Pitch clamping for partial panoramas |
| `src/app/tour-proxy/page.tsx` | Standalone tour player (iframe-embeddable proxy) |

---

## Data Model

```typescript
interface TourConfig {
 scenes: TourScene[];
 first_scene_id: string;
}

interface TourScene {
 id: string;
 title: string; // Room name (e.g. "Salon", "Chambre 1")
 type: 'equirectangular';
 image_url: string; // Full-resolution panorama URL
 thumbnail_url: string; // Preview thumbnail
 initial_view: {
 pitch: number; // Initial vertical angle (degrees)
 yaw: number; // Initial horizontal angle (degrees)
 zoom: number; // Initial zoom level (0-100)
 };
 hotspots: TourHotspot[];
 // Partial panorama parameters (optional)
 haov?: number; // Horizontal angle of view (degrees, default: 360)
 vaov?: number; // Vertical angle of view (degrees, default: 180)
 vOffset?: number; // Vertical offset
 is_partial_pano: boolean;
 processing: boolean; // True while backend is processing the image
}

interface TourHotspot {
 pitch: number;
 yaw: number;
 target_scene: string; // Scene ID to navigate to
 label: string; // Displayed tooltip text
 type: 'arrow' | 'info';
}
```

---

## Key Code Snippets

### Upload Tour Scenes

```typescript
// src/services/ads.service.ts
async uploadTourScenes(adId: string, formData: FormData) {
 const { data } = await api.post(`/ads/${adId}/tour/scenes`, formData, {
 timeout: 600_000, // 10-minute timeout for large panoramas
 headers: { 'Content-Type': 'multipart/form-data' },
 });
 return data; // { scenes_count, config }
},
```

### Update Hotspots

```typescript
// src/services/ads.service.ts
async updateHotspots(adId: string, sceneId: string, hotspots: TourHotspot[]) {
 const { data } = await api.patch(
 `/ads/${adId}/tour/scenes/${sceneId}/hotspots`,
 { hotspots }
 );
 return data; // { message }
},
```

### PSV Viewer Integration

```typescript
// src/components/ads/AdTour.tsx
import { Viewer } from '@photo-sphere-viewer/core';
import { MarkersPlugin } from '@photo-sphere-viewer/markers-plugin';
import { VirtualTourPlugin } from '@photo-sphere-viewer/virtual-tour-plugin';

const viewer = new Viewer({
 container: containerRef.current,
 panorama: scene.image_url,
 panoData: inferEquirectangularPanoData(scene), // Auto-detect from scene metadata
 plugins: [
 [MarkersPlugin, { markers: scene.hotspots.map(toMarker) }],
 [VirtualTourPlugin, {
 nodes: tourConfig.scenes.map(sceneToNode),
 }],
 ],
});
```

### Partial Panorama Support

```typescript
// src/lib/inferEquirectangularPanoData.ts
export function inferEquirectangularPanoData(scene: TourScene) {
 if (!scene.is_partial_pano) return undefined;
 return {
 fullWidth: scene.haov ? Math.round((360 / scene.haov) * imageWidth) : undefined,
 fullHeight: scene.vaov ? Math.round((180 / scene.vaov) * imageHeight) : undefined,
 croppedX: 0,
 croppedY: scene.vOffset ? Math.round(scene.vOffset * imageHeight) : 0,
 };
}
```

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Body | Response |
|---|---|---|---|
| `GET` | `/api/v1/ads/:id/tour` | — | `{ has_tour, scenes_count, tour_published_at, config: TourConfig }` |
| `POST` | `/api/v1/ads/:id/tour/scenes` | `FormData` (panorama images, 600s timeout) | `{ scenes_count, config: TourConfig }` |
| `PATCH` | `/api/v1/ads/:id/tour/scenes/:sceneId/hotspots` | `{ hotspots: TourHotspot[] }` | `{ message }` |
| `DELETE` | `/api/v1/ads/:id/tour` | — | `{ message }` |

### Tour Fetch Response

```json
{
 "has_tour": true,
 "scenes_count": 5,
 "tour_published_at": "2024-01-15T10:30:00Z",
 "config": {
 "first_scene_id": "scene-uuid",
 "scenes": [
 {
 "id": "scene-uuid",
 "title": "Salon",
 "image_url": "https://cdn.keyhome.cm/tours/...",
 "thumbnail_url": "https://cdn.keyhome.cm/tours/thumb...",
 "initial_view": { "pitch": 0, "yaw": 0, "zoom": 50 },
 "hotspots": [],
 "is_partial_pano": false,
 "processing": false
 }
 ]
 }
}
```

---

## Tour Proxy Route

The `/tour-proxy` page provides a **standalone, embeddable tour player** that can be used in iframes or external integrations. It accepts the tour configuration via query parameters or `postMessage`.

---

## Backend Processing

After upload, scenes are processed asynchronously by `ProcessTourSceneJob`:
- Generates tiles (for tiled panoramas)
- Creates thumbnails
- Backfills metadata (`haov`, `vaov`, `vOffset`) via `BackfillTourPanoMetadata` artisan command

The frontend polls or relies on the `processing: boolean` flag to show a loading state until processing is complete.

---

## Related Documentation

- [Ad Management](./ad-management.md)
- [Ad Details (viewer)](../visitor/ad-details.md)
