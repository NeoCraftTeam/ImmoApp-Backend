/**
 * useMyAds — vérifie que le shape ad-feed cursor-paginé survit aux
 * payloads partiels. La logique réside dans le code des écrans
 * consommateurs (flatMap pages.data) ; on duplique localement pour
 * tester sans wirer TanStack.
 */

type Pages = { pages?: unknown };

function selectAds(data: Pages | undefined): unknown[] {
  return Array.isArray(data?.pages)
    ? data!.pages.flatMap((p) => {
        const d = (p as { data?: unknown }).data;
        return Array.isArray(d) ? d : [];
      })
    : [];
}

describe('useMyAds select resilience', () => {
  it('renvoie [] si data undefined', () => {
    expect(selectAds(undefined)).toEqual([]);
  });
  it('renvoie [] si pages absent', () => {
    expect(selectAds({} as Pages)).toEqual([]);
  });
  it('renvoie [] si pages est objet', () => {
    expect(selectAds({ pages: { foo: 'bar' } } as unknown as Pages)).toEqual([]);
  });
  it('flatMap normal', () => {
    const pages: Pages = {
      pages: [{ data: [{ id: 'a' }] }, { data: [{ id: 'b' }, { id: 'c' }] }],
    };
    expect(selectAds(pages)).toHaveLength(3);
  });
  it('skip page avec data null', () => {
    const pages: Pages = {
      pages: [{ data: [{ id: 'a' }] }, { data: null }],
    };
    expect(selectAds(pages)).toEqual([{ id: 'a' }]);
  });
});
