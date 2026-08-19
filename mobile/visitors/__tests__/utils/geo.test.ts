import {
  extractRouteCoords,
  formatDistance,
  haversineKm,
  walkingMinutes,
} from '../../src/utils/geo';
import type { DirectionsResponse } from '../../src/hooks/useDirections';

describe('haversineKm', () => {
  it('returns 0 for identical points', () => {
    const p = { latitude: 4.05, longitude: 9.7 }; // Douala
    expect(haversineKm(p, p)).toBe(0);
  });

  it('matches a known city pair (Douala ↔ Yaoundé ≈ 200 km)', () => {
    const douala = { latitude: 4.05, longitude: 9.7 };
    const yaounde = { latitude: 3.87, longitude: 11.52 };
    const km = haversineKm(douala, yaounde);
    expect(km).toBeGreaterThan(190);
    expect(km).toBeLessThan(220);
  });

  it('is symmetric (A→B = B→A)', () => {
    const a = { latitude: 0, longitude: 0 };
    const b = { latitude: 10, longitude: 20 };
    expect(haversineKm(a, b)).toBeCloseTo(haversineKm(b, a), 6);
  });
});

describe('formatDistance', () => {
  it('formats sub-kilometre as metres', () => {
    expect(formatDistance(0.85)).toBe('850 m');
    expect(formatDistance(0.001)).toBe('1 m');
  });

  it('formats km with one decimal + French comma', () => {
    expect(formatDistance(2.34)).toBe('2,3 km');
    expect(formatDistance(10)).toBe('10,0 km');
  });

  it('handles NaN / negatives gracefully', () => {
    expect(formatDistance(NaN)).toBe('—');
    expect(formatDistance(-1)).toBe('—');
  });
});

describe('walkingMinutes', () => {
  it('uses 5 km/h pace', () => {
    // 1 km à 5 km/h = 12 min
    expect(walkingMinutes(1)).toBe(12);
  });

  it('floors to at least 1 min for very short distances', () => {
    expect(walkingMinutes(0.01)).toBe(1);
  });
});

describe('extractRouteCoords', () => {
  it('extracts coordinates correctly for a valid FeatureCollection with a LineString feature', () => {
    const mockDirections = {
      data: {
        summary: {
          distance_m: 1000,
          duration_s: 600,
          distance_label: '1,0 km',
          duration_label: '10 min',
        },
        profile: 'driving-car',
        profile_label: 'Driving',
        geojson: {
          type: 'FeatureCollection',
          features: [
            {
              type: 'Feature',
              properties: {},
              geometry: {
                type: 'LineString',
                coordinates: [
                  [10, 20],
                  [30, 40],
                ],
              },
            },
          ],
        },
      },
    } as unknown as DirectionsResponse;

    const coords = extractRouteCoords(mockDirections);
    expect(coords).toEqual([
      { latitude: 20, longitude: 10 },
      { latitude: 40, longitude: 30 },
    ]);
  });

  it('returns null for an empty or null geojson', () => {
    expect(extractRouteCoords(undefined)).toBeNull();
    expect(
      extractRouteCoords({ data: { geojson: null } } as unknown as DirectionsResponse),
    ).toBeNull();
    expect(
      extractRouteCoords({
        data: { geojson: { type: 'FeatureCollection', features: [] } },
      } as unknown as DirectionsResponse),
    ).toBeNull();
  });

  it('returns null for a feature with non-LineString geometry', () => {
    const mockDirections = {
      data: {
        geojson: {
          type: 'FeatureCollection',
          features: [
            {
              type: 'Feature',
              properties: {},
              geometry: {
                type: 'Point',
                coordinates: [10, 20],
              },
            },
          ],
        },
      },
    } as unknown as DirectionsResponse;

    expect(extractRouteCoords(mockDirections)).toBeNull();
  });
});
