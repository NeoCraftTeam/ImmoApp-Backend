/**
 * useAdReviews — select payload reviews/meta. Pure logic check, mirror
 * de l'implémentation visiteur — on garantit que le select reste
 * robuste aux shapes incomplets du backend.
 */

interface Payload {
  data?: unknown;
  meta?: { average_rating?: number | null; reviews_count?: number };
}

function selectReviews(payload: Payload | undefined) {
  const reviews = Array.isArray(payload?.data) ? (payload!.data as unknown[]) : [];
  return {
    reviews,
    averageRating: payload?.meta?.average_rating ?? null,
    count: payload?.meta?.reviews_count ?? reviews.length,
  };
}

describe('useAdReviews select', () => {
  it('payload undefined', () => {
    const r = selectReviews(undefined);
    expect(r.reviews).toEqual([]);
    expect(r.averageRating).toBeNull();
    expect(r.count).toBe(0);
  });

  it('payload complet', () => {
    const r = selectReviews({
      data: [{ id: 1 }, { id: 2 }],
      meta: { average_rating: 4.5, reviews_count: 10 },
    });
    expect(r.reviews).toHaveLength(2);
    expect(r.averageRating).toBe(4.5);
    expect(r.count).toBe(10);
  });

  it('count fallback sur length', () => {
    const r = selectReviews({ data: [{ id: 1 }] });
    expect(r.count).toBe(1);
  });

  it('data non-array → reviews []', () => {
    const r = selectReviews({ data: null, meta: { average_rating: 3.2 } });
    expect(r.reviews).toEqual([]);
    expect(r.averageRating).toBe(3.2);
  });
});
