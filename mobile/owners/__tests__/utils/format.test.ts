import { formatCompact, formatDate, formatFcfa, formatMoney } from '@/utils/format';

describe('format helpers', () => {
  describe('formatMoney', () => {
    it('renvoie — pour null/undefined/NaN', () => {
      expect(formatMoney(null)).toBe('—');
      expect(formatMoney(undefined)).toBe('—');
      expect(formatMoney(NaN)).toBe('—');
    });

    it('formate avec séparateur de milliers', () => {
      const v = formatMoney(1234567);
      // séparateur peut être espace insécable selon ICU — on accepte les deux
      expect(v.replace(/\s/g, '')).toBe('1234567');
      expect(v.length).toBeGreaterThan(7);
    });
  });

  describe('formatFcfa', () => {
    it('ajoute le suffixe FCFA', () => {
      expect(formatFcfa(50000)).toMatch(/FCFA$/);
    });
    it('renvoie — pour null', () => {
      expect(formatFcfa(null)).toBe('—');
    });
  });

  describe('formatCompact', () => {
    it('renvoie 0 pour null/NaN', () => {
      expect(formatCompact(null)).toBe('0');
      expect(formatCompact(NaN)).toBe('0');
    });
    it('garde tel quel pour < 1000', () => {
      expect(formatCompact(999)).toBe('999');
    });
    it('compacte en k pour milliers', () => {
      expect(formatCompact(1234)).toBe('1.2k');
      expect(formatCompact(15000)).toBe('15k');
    });
    it('compacte en M pour millions', () => {
      expect(formatCompact(1_500_000)).toBe('1.5M');
    });
  });

  describe('formatDate', () => {
    it('renvoie — pour input invalide', () => {
      expect(formatDate(null)).toBe('—');
      expect(formatDate('not-a-date')).toBe('—');
    });
    it('formate une ISO valide', () => {
      const out = formatDate('2026-06-22T10:00:00Z');
      expect(out).toMatch(/2026/);
    });
  });
});
