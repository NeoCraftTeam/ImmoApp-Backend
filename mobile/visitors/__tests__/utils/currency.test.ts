import { convertFromXAF, formatFromXAF, symbolFor } from '@/services/currency';

/**
 * La devise d'affichage est résolue automatiquement depuis l'appareil (aucun
 * sélecteur manuel). Ces tests couvrent les helpers de formatage/conversion
 * purs avec une devise cible explicite, indépendamment de la locale résolue.
 */
describe('currency display helpers', () => {
  describe('formatFromXAF', () => {
    it('rend XAF en entier suffixé FCFA', () => {
      const out = formatFromXAF(230000, 'XAF');
      expect(out.replace(/\s/g, '')).toBe('230000FCFA');
    });

    it('traite XOF comme XAF (pegé 1:1, suffixe FCFA)', () => {
      const out = formatFromXAF(150000, 'XOF');
      expect(out.replace(/\s/g, '')).toBe('150000FCFA');
    });

    it('convertit et préfixe le symbole pour EUR (2 décimales)', () => {
      const out = formatFromXAF(1000000, 'EUR');
      expect(out.startsWith('€')).toBe(true);
      expect(out).toMatch(/,\d{2}$/);
    });

    it('convertit et préfixe le symbole pour USD', () => {
      const out = formatFromXAF(1000000, 'USD');
      expect(out.startsWith('$')).toBe(true);
    });

    it('suffixe le symbole pour les devises non occidentales (NGN)', () => {
      const out = formatFromXAF(1000, 'NGN');
      expect(out.endsWith('₦')).toBe(true);
    });

    it('retombe sur XAF quand la devise est inconnue (jamais de mauvais symbole)', () => {
      const out = formatFromXAF(230000, 'ZZZ');
      expect(out.replace(/\s/g, '')).toBe('230000FCFA');
    });
  });

  describe('convertFromXAF', () => {
    it('renvoie le montant inchangé pour XAF/XOF (peg 1:1)', () => {
      expect(convertFromXAF(230000, 'XAF')).toBe(230000);
      expect(convertFromXAF(230000, 'XOF')).toBe(230000);
    });

    it('applique le taux pour une devise convertible', () => {
      expect(convertFromXAF(1000000, 'EUR')).toBeGreaterThan(0);
      expect(convertFromXAF(1000000, 'EUR')).toBeLessThan(1000000);
    });

    it('retombe sur le montant XAF quand la devise est inconnue', () => {
      expect(convertFromXAF(230000, 'ZZZ')).toBe(230000);
    });
  });

  describe('symbolFor', () => {
    it('renvoie FCFA pour XAF/XOF et le code brut pour l’inconnu', () => {
      expect(symbolFor('XAF')).toBe('FCFA');
      expect(symbolFor('XOF')).toBe('FCFA');
      expect(symbolFor('ZZZ')).toBe('ZZZ');
    });
  });
});
