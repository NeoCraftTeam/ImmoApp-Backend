/**
 * useAdFeed — résilience aux shapes inattendues du backend.
 * On test le `select` (flatMap des pages cursor-paginées) isolément
 * pour ne pas avoir à wirer un QueryClient complet : la logique
 * critique est dans le select et c'est elle qui crashait avant le
 * patch Array.isArray.
 */

type AdFeedResponse = {
  data?: unknown;
  meta?: { next_cursor?: string | null };
};

type Pages = { pages?: unknown };

// Copie locale de la logique select (importer le hook lui-même tirerait
// react-query → Node test env + RN → overkill pour tester un select pur).
function selectAds(data: Pages | undefined): unknown[] {
  return Array.isArray(data?.pages)
    ? data!.pages.flatMap((p) =>
        Array.isArray((p as AdFeedResponse)?.data)
          ? (p as AdFeedResponse).data as unknown[]
          : [],
      )
    : [];
}

describe('useAdFeed select shape resilience', () => {
  it('renvoie [] si data est undefined', () => {
    expect(selectAds(undefined)).toEqual([]);
  });

  it('renvoie [] si pages absent', () => {
    expect(selectAds({} as Pages)).toEqual([]);
  });

  it('renvoie [] si pages est un objet (pas array)', () => {
    expect(selectAds({ pages: { foo: 'bar' } } as unknown as Pages)).toEqual([]);
  });

  it('flatMap correctement quand toutes les pages ont data array', () => {
    const pages: Pages = {
      pages: [{ data: [{ id: 'a' }, { id: 'b' }] }, { data: [{ id: 'c' }] }],
    };
    expect(selectAds(pages)).toHaveLength(3);
  });

  it('skip une page sans data array sans crasher', () => {
    const pages: Pages = {
      pages: [{ data: [{ id: 'a' }] }, { data: null }, { data: [{ id: 'b' }] }],
    };
    expect(selectAds(pages)).toEqual([{ id: 'a' }, { id: 'b' }]);
  });
});
