/**
 * Vérifie la logique `refetchInterval` du hook `usePublicPaymentStatus` :
 * - while pending → 3 s
 * - terminal (success/failed/cancelled) → false (stoppe le poll)
 *
 * Duplicate locale pour éviter d'embarquer React Query en test pur.
 */

type Status = 'pending' | 'success' | 'failed' | 'cancelled' | 'succeeded';

function refetchInterval(currentStatus: Status | undefined): number | false {
  return currentStatus === 'pending' || !currentStatus ? 3000 : false;
}

describe('payment status polling', () => {
  it('poll toutes les 3 s tant que pending', () => {
    expect(refetchInterval('pending')).toBe(3000);
  });

  it('poll si statut indéfini (premier appel)', () => {
    expect(refetchInterval(undefined)).toBe(3000);
  });

  it('stoppe sur success', () => {
    expect(refetchInterval('success')).toBe(false);
    expect(refetchInterval('succeeded')).toBe(false);
  });

  it('stoppe sur failed', () => {
    expect(refetchInterval('failed')).toBe(false);
  });

  it('stoppe sur cancelled', () => {
    expect(refetchInterval('cancelled')).toBe(false);
  });
});
