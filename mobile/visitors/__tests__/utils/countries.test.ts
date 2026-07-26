import { COUNTRIES, splitPhoneNumber } from '@/utils/countries';

describe('splitPhoneNumber', () => {
  it('reconnaît un numéro camerounais complet', () => {
    const { country, local } = splitPhoneNumber('+237650000001');
    expect(country.code).toBe('CM');
    expect(local).toBe('650000001');
  });

  it('ne confond pas les indicatifs préfixes (+225 vs +22…)', () => {
    expect(splitPhoneNumber('+2250701020304').country.code).toBe('CI');
    expect(splitPhoneNumber('+22990010203').country.code).toBe('BJ');
  });

  it('retombe sur le Cameroun pour un numéro sans indicatif', () => {
    const { country, local } = splitPhoneNumber('650000001');
    expect(country.code).toBe('CM');
    expect(local).toBe('650000001');
  });

  it('retombe sur le Cameroun pour un indicatif inconnu', () => {
    expect(splitPhoneNumber('+99912345').country.code).toBe('CM');
  });

  it('les indicatifs sont uniques par pays (hors +1 US/CA)', () => {
    const dials = COUNTRIES.filter((c) => c.dial !== '+1').map((c) => c.dial);
    expect(new Set(dials).size).toBe(dials.length);
  });
});
