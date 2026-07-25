import { parseCreditsBalance } from '@/utils/credits-balance';
import { resolveMediaUrl } from '@/lib/media-url';

jest.mock('@/api/client', () => ({
  RESOLVED_BASE_URL: 'https://api.keyhome.app/api/v1',
}));

describe('parseCreditsBalance', () => {
  it('reads point_balance at root', () => {
    expect(parseCreditsBalance({ point_balance: 27 })).toBe(27);
  });

  it('reads nested data.point_balance', () => {
    expect(parseCreditsBalance({ data: { point_balance: 11 } })).toBe(11);
  });

  it('falls back to balance aliases', () => {
    expect(parseCreditsBalance({ balance: 5 })).toBe(5);
    expect(parseCreditsBalance({ credit_balance: 3 })).toBe(3);
  });

  it('returns 0 when no balance field is present', () => {
    expect(parseCreditsBalance({})).toBe(0);
  });
});

describe('resolveMediaUrl', () => {
  it('returns null for empty values', () => {
    expect(resolveMediaUrl(null)).toBeNull();
    expect(resolveMediaUrl('')).toBeNull();
  });

  it('passes through absolute https URLs', () => {
    expect(resolveMediaUrl('https://cdn.example.com/a.jpg')).toBe(
      'https://cdn.example.com/a.jpg',
    );
  });

  it('normalises protocol-relative URLs', () => {
    expect(resolveMediaUrl('//cdn.example.com/a.jpg')).toBe(
      'https://cdn.example.com/a.jpg',
    );
  });

  it('prefixes legacy relative avatar paths', () => {
    expect(resolveMediaUrl('avatars/default.png')).toBe(
      'https://api.keyhome.app/storage/avatars/default.png',
    );
  });
});
