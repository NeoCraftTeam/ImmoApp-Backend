/**
 * `extractBalance` est la logique pure qui normalise les 3 shapes
 * possibles renvoyés par GET /credits/balance. Copie locale pour
 * éviter de tirer `apiClient` (qui charge expo-constants natif).
 */

function extractBalance(payload: unknown): number {
  if (typeof payload === 'number') return payload;
  if (payload && typeof payload === 'object') {
    const obj = payload as Record<string, unknown>;
    if (typeof obj.point_balance === 'number') return obj.point_balance;
    if (typeof obj.balance === 'number') return obj.balance;
    const inner = obj.data as Record<string, unknown> | undefined;
    if (inner) {
      if (typeof inner.point_balance === 'number') return inner.point_balance;
      if (typeof inner.balance === 'number') return inner.balance;
    }
  }
  return 0;
}

describe('extractBalance', () => {
  it('renvoie 0 pour null/undefined/string', () => {
    expect(extractBalance(null)).toBe(0);
    expect(extractBalance(undefined)).toBe(0);
    expect(extractBalance('1234')).toBe(0);
  });

  it('renvoie le number directement', () => {
    expect(extractBalance(500)).toBe(500);
  });

  it('lit point_balance prioritairement', () => {
    expect(extractBalance({ point_balance: 250, balance: 99 })).toBe(250);
  });

  it('fallback sur balance si pas de point_balance', () => {
    expect(extractBalance({ balance: 1000 })).toBe(1000);
  });

  it('plonge dans .data si le shape est wrapped', () => {
    expect(extractBalance({ data: { point_balance: 720 } })).toBe(720);
    expect(extractBalance({ data: { balance: 320 } })).toBe(320);
  });

  it('renvoie 0 si toutes les clés sont absentes', () => {
    expect(extractBalance({ foo: 'bar' })).toBe(0);
    expect(extractBalance({ data: {} })).toBe(0);
  });
});
