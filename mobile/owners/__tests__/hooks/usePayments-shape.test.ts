import type { PaymentHistory, PaymentStatus } from '@/types/payment';

function summarisePayments(history: PaymentHistory | undefined): {
  total: number;
  successful: number;
  pending: number;
  refunded: number;
} {
  const list = Array.isArray(history?.data) ? history!.data : [];
  const sumIf = (s: PaymentStatus) =>
    list.filter((p) => p.status === s).reduce((acc, p) => acc + (p.amount ?? 0), 0);
  return {
    total: list.length,
    successful: sumIf('succeeded'),
    pending: sumIf('pending'),
    refunded: sumIf('refunded'),
  };
}

describe('payment summary', () => {
  it('vide pour history undefined', () => {
    expect(summarisePayments(undefined)).toEqual({
      total: 0,
      successful: 0,
      pending: 0,
      refunded: 0,
    });
  });

  it('agrège correctement', () => {
    const r = summarisePayments({
      data: [
        { id: '1', tx_ref: 't1', amount: 1000, status: 'succeeded', created_at: '2026-01-01' },
        { id: '2', tx_ref: 't2', amount: 500, status: 'pending', created_at: '2026-01-02' },
        { id: '3', tx_ref: 't3', amount: 200, status: 'refunded', created_at: '2026-01-03' },
        { id: '4', tx_ref: 't4', amount: 800, status: 'succeeded', created_at: '2026-01-04' },
      ],
    });
    expect(r.total).toBe(4);
    expect(r.successful).toBe(1800);
    expect(r.pending).toBe(500);
    expect(r.refunded).toBe(200);
  });

  it('survit aux data non-array', () => {
    expect(summarisePayments({ data: null as unknown as PaymentHistory['data'] })).toEqual({
      total: 0,
      successful: 0,
      pending: 0,
      refunded: 0,
    });
  });
});
