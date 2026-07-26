import type { Ad } from '@/types/ad';
import { formatChargeAmount, hasSupplementaryInfo } from '@/utils/supplementary-info';

const BASE_AD = {
  id: '1',
  title: 'Test',
  description: '',
  adresse: '',
  price: null,
  status: 'published',
  is_currently_available: true,
  is_unlocked: true,
  reviews_count: 0,
  images: [],
  total_images: 0,
  has_3d_tour: false,
} as Ad;

describe('hasSupplementaryInfo', () => {
  it('faux quand aucun champ supplémentaire', () => {
    expect(hasSupplementaryInfo(BASE_AD)).toBe(false);
  });

  it('vrai dès qu\'un champ est renseigné', () => {
    expect(hasSupplementaryInfo({ ...BASE_AD, deposit_amount: '2 mois' })).toBe(true);
    expect(hasSupplementaryInfo({ ...BASE_AD, charges_eau: 5000 })).toBe(true);
    expect(hasSupplementaryInfo({ ...BASE_AD, property_condition_pdf: 'https://x/pdf' })).toBe(true);
  });
});

describe('formatChargeAmount', () => {
  it('formate les montants numériques en FCFA/mois', () => {
    expect(formatChargeAmount(50000)).toBe(`${(50000).toLocaleString('fr-FR')} FCFA/mois`);
    expect(formatChargeAmount('7500')).toBe(`${(7500).toLocaleString('fr-FR')} FCFA/mois`);
  });

  it('laisse les valeurs texte telles quelles', () => {
    expect(formatChargeAmount('selon consommation')).toBe('selon consommation');
  });
});
