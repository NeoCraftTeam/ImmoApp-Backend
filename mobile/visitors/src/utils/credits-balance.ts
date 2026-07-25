/**
 * Parse GET /credits/balance (or verify/unlock payloads) into a numeric balance.
 * Accepts point_balance / balance / credit_balance at root or under `data`.
 */
export function parseCreditsBalance(payload: unknown): number {
  if (typeof payload === 'number') {
    return payload;
  }

  const root = (payload ?? {}) as Record<string, unknown>;
  const nested = (root.data ?? {}) as Record<string, unknown>;

  for (const src of [root, nested]) {
    for (const key of ['point_balance', 'balance', 'credit_balance', 'credits'] as const) {
      const value = src[key];
      if (typeof value === 'number') {
        return value;
      }
    }
  }

  return 0;
}
