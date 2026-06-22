import { formatDistance, haversineKm, walkingMinutes } from '@/utils/geo';

describe('geo utils', () => {
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
});
