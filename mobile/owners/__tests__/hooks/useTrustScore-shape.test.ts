import type { TrustScore } from '@/types/proservice';

function selectTrust(p: { data: TrustScore } | TrustScore | null | undefined): TrustScore | null {
  if (!p) return null;
  const inner = (p as { data?: TrustScore }).data;
  return inner ?? (p as TrustScore);
}

describe('useTrustScore select', () => {
  it('renvoie null pour input null', () => {
    expect(selectTrust(null)).toBeNull();
    expect(selectTrust(undefined)).toBeNull();
  });

  it('extrait .data si présent (réponse "wrapped")', () => {
    const wrapped = { data: { score: 75 } as TrustScore };
    expect(selectTrust(wrapped)?.score).toBe(75);
  });

  it('renvoie tel quel pour shape plate', () => {
    const flat: TrustScore = { score: 62 };
    expect(selectTrust(flat)?.score).toBe(62);
  });
});
