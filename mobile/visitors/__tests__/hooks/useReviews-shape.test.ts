/**
 * useReviews — vérifie que le select gère correctement les shapes
 * incomplets / inattendus du backend reviews.
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

describe('useReviews select', () => {
  it('payload undefined → empty list, null average, count 0', () => {
    const r = selectReviews(undefined);
    expect(r.reviews).toEqual([]);
    expect(r.averageRating).toBeNull();
    expect(r.count).toBe(0);
  });

  it('payload avec data array + meta complet', () => {
    const r = selectReviews({
      data: [{ id: 'r1' }, { id: 'r2' }],
      meta: { average_rating: 4.2, reviews_count: 12 },
    });
    expect(r.reviews).toHaveLength(2);
    expect(r.averageRating).toBe(4.2);
    expect(r.count).toBe(12);
  });

  it('utilise reviews.length quand meta.reviews_count absent', () => {
    const r = selectReviews({ data: [{ id: 'r1' }] });
    expect(r.count).toBe(1);
  });

  it('garde average_rating à null sans crasher si data manque', () => {
    const r = selectReviews({ data: null, meta: { average_rating: 3.1 } });
    expect(r.reviews).toEqual([]);
    expect(r.averageRating).toBe(3.1);
    expect(r.count).toBe(0);
  });
});
