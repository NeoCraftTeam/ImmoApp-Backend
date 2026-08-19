/**
 * Geo utilities — haversine + display formatting. Kept local to the
 * mobile workspace so we don't drag the whole web `geo.service.ts`
 * dependency tree (which depends on `@turf/turf`).
 */

const EARTH_RADIUS_KM = 6371;

/** Great-circle distance between two `{ latitude, longitude }` points, in kilometres. */
export function haversineKm(
  a: { latitude: number; longitude: number },
  b: { latitude: number; longitude: number },
): number {
  const toRad = (deg: number): number => (deg * Math.PI) / 180;
  const dLat = toRad(b.latitude - a.latitude);
  const dLng = toRad(b.longitude - a.longitude);
  const lat1 = toRad(a.latitude);
  const lat2 = toRad(b.latitude);

  const s =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
  return 2 * EARTH_RADIUS_KM * Math.asin(Math.sqrt(s));
}

/**
 * Human-readable distance: <1 km → "850 m", >=1 km → "2.3 km".
 * Always French (no localisation needed yet — this app is FR-only).
 */
export function formatDistance(km: number): string {
  if (!Number.isFinite(km) || km < 0) return '—';
  if (km < 1) {
    return `${Math.round(km * 1000)} m`;
  }
  return `${km.toFixed(1).replace('.', ',')} km`;
}

/** Walking time estimate from distance (5 km/h). */
export function walkingMinutes(km: number): number {
  return Math.max(1, Math.round((km / 5) * 60));
}

export function extractRouteCoords(
  directions?: import('../hooks/useDirections').DirectionsResponse | null
): { latitude: number; longitude: number }[] | null {
  const feature = directions?.data?.geojson?.features?.[0];
  if (!feature || feature.geometry?.type !== 'LineString') return null;
  const coords = (feature.geometry as { type: 'LineString'; coordinates: number[][] }).coordinates;
  if (!coords || coords.length === 0) return null;
  return coords
    .map((c: number[]) => ({ latitude: c[1], longitude: c[0] }))
    .filter(
      (c): c is { latitude: number; longitude: number } =>
        typeof c.latitude === 'number' && typeof c.longitude === 'number',
    );
}
