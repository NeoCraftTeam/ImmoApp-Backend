/**
 * chat-delta — cœur pur de la sync incrémentale façon WhatsApp Web.
 *
 * On teste la logique isolée (curseur UTC, dédoublonnage uuid, ordre
 * optimistes-en-dernier, boucle de rattrapage `has_more`, no-op cache froid)
 * sans wirer react-query / AppState / Reverb — c'est là qu'était le risque.
 */
import type { ConversationMessage } from '@/types/conversation';
import {
  DELTA_SYNC_MAX_PAGES,
  isOptimistic,
  latestSettledCursor,
  mergeDelta,
  mergeFreshPage,
  OPTIMISTIC_PREFIX,
  runDeltaSync,
  type DeltaPage,
} from '@/hooks/chat-delta';

const T1 = '2026-08-21T10:00:00.000Z';
const T2 = '2026-08-21T10:05:00.000Z';
const T3 = '2026-08-21T10:10:00.000Z';

function msg(
  uuid: string,
  createdAt: string,
  extra: Partial<ConversationMessage> = {},
): ConversationMessage {
  return {
    uuid,
    conversation_uuid: 'conv-1',
    body: uuid,
    sender_id: 'peer',
    created_at: createdAt,
    ...extra,
  };
}

function page(data: ConversationMessage[], hasMore = false): DeltaPage {
  return { data, has_more: hasMore };
}

describe('isOptimistic', () => {
  it('détecte le préfixe temp:', () => {
    expect(isOptimistic(msg(`${OPTIMISTIC_PREFIX}42`, T1))).toBe(true);
  });

  it('détecte le flag is_optimistic', () => {
    expect(isOptimistic(msg('m1', T1, { is_optimistic: true }))).toBe(true);
  });

  it('un message settled normal n’est pas optimiste', () => {
    expect(isOptimistic(msg('m1', T1))).toBe(false);
  });
});

describe('latestSettledCursor', () => {
  it('renvoie le created_at du dernier message settled', () => {
    expect(latestSettledCursor([msg('m1', T1), msg('m2', T2)])).toBe(T2);
  });

  it('ignore les optimistes en fin de liste (borne = dernier settled)', () => {
    const list = [msg('m1', T1), msg(`${OPTIMISTIC_PREFIX}x`, T3)];
    expect(latestSettledCursor(list)).toBe(T1);
  });

  it('renvoie null si aucun message settled', () => {
    expect(latestSettledCursor([msg(`${OPTIMISTIC_PREFIX}x`, T3)])).toBeNull();
    expect(latestSettledCursor([])).toBeNull();
  });
});

describe('mergeFreshPage', () => {
  it('sur cache vide, renvoie simplement la page fraîche', () => {
    const fresh = [msg('m1', T1), msg('m2', T2)];
    expect(mergeFreshPage([], fresh).map((m) => m.uuid)).toEqual(['m1', 'm2']);
  });

  it('préserve la traîne ancienne + page fraîche + optimistes en dernier', () => {
    const prev = [
      msg('old', T1),
      msg('m2', T2),
      msg(`${OPTIMISTIC_PREFIX}p`, T3),
    ];
    const fresh = [msg('m2', T2), msg('m3', T3)];
    expect(mergeFreshPage(prev, fresh).map((m) => m.uuid)).toEqual([
      'old',
      'm2',
      'm3',
      `${OPTIMISTIC_PREFIX}p`,
    ]);
  });
});

describe('mergeDelta', () => {
  it('append les nouveaux messages en dédoublonnant par uuid', () => {
    const list = [msg('m1', T1), msg('m2', T2)];
    const fresh = [msg('m2', T2), msg('m3', T3)]; // m2 = borne inclusive
    expect(mergeDelta(list, fresh).map((m) => m.uuid)).toEqual(['m1', 'm2', 'm3']);
  });

  it('garde les optimistes en dernier après le merge', () => {
    const list = [msg('m1', T1), msg(`${OPTIMISTIC_PREFIX}p`, T3)];
    const fresh = [msg('m2', T2)];
    expect(mergeDelta(list, fresh).map((m) => m.uuid)).toEqual([
      'm1',
      'm2',
      `${OPTIMISTIC_PREFIX}p`,
    ]);
  });

  it('renvoie la liste inchangée si rien de nouveau', () => {
    const list = [msg('m1', T1), msg('m2', T2)];
    const out = mergeDelta(list, [msg('m2', T2)]);
    expect(out).toBe(list);
  });
});

describe('runDeltaSync', () => {
  it('no-op sur cache vide — la première page complète gère le nouvel appareil', async () => {
    const fetchAfter = jest.fn<Promise<DeltaPage>, [string]>();
    await runDeltaSync({ getMessages: () => [], applyFresh: jest.fn(), fetchAfter });
    expect(fetchAfter).not.toHaveBeenCalled();
  });

  it('no-op si la liste ne contient que des optimistes (pas de borne settled)', async () => {
    const fetchAfter = jest.fn<Promise<DeltaPage>, [string]>();
    await runDeltaSync({
      getMessages: () => [msg(`${OPTIMISTIC_PREFIX}p`, T3)],
      applyFresh: jest.fn(),
      fetchAfter,
    });
    expect(fetchAfter).not.toHaveBeenCalled();
  });

  it('tire ?after sur le curseur UTC du dernier settled et applique le lot', async () => {
    const fetchAfter = jest.fn().mockResolvedValue(page([msg('m3', T3)]));
    const applyFresh = jest.fn();
    await runDeltaSync({
      getMessages: () => [msg('m1', T1), msg('m2', T2)],
      applyFresh,
      fetchAfter,
    });
    expect(fetchAfter).toHaveBeenCalledWith(T2);
    expect(fetchAfter).toHaveBeenCalledTimes(1);
    expect(applyFresh).toHaveBeenCalledWith([expect.objectContaining({ uuid: 'm3' })]);
  });

  it('borne sur le dernier settled même avec un optimiste en attente', async () => {
    const fetchAfter = jest.fn().mockResolvedValue(page([]));
    await runDeltaSync({
      getMessages: () => [msg('m1', T1), msg(`${OPTIMISTIC_PREFIX}p`, T3)],
      applyFresh: jest.fn(),
      fetchAfter,
    });
    expect(fetchAfter).toHaveBeenCalledWith(T1);
  });

  it('suit has_more en avançant le curseur (rattrapage longue absence)', async () => {
    const fetchAfter = jest
      .fn()
      .mockResolvedValueOnce(page([msg('m2', T2)], true))
      .mockResolvedValueOnce(page([msg('m3', T3)], false));
    await runDeltaSync({
      getMessages: () => [msg('m1', T1)],
      applyFresh: jest.fn(),
      fetchAfter,
    });
    expect(fetchAfter).toHaveBeenCalledTimes(2);
    expect(fetchAfter).toHaveBeenNthCalledWith(1, T1);
    expect(fetchAfter).toHaveBeenNthCalledWith(2, T2);
  });

  it('stoppe si le curseur n’avance plus (garde anti-boucle sur borne inclusive)', async () => {
    // has_more=true mais data ne contient que la borne → newest === after → stop.
    const fetchAfter = jest.fn().mockResolvedValue(page([msg('m1', T1)], true));
    await runDeltaSync({
      getMessages: () => [msg('m1', T1)],
      applyFresh: jest.fn(),
      fetchAfter,
    });
    expect(fetchAfter).toHaveBeenCalledTimes(1);
  });

  it('respecte le cap maxPages', async () => {
    const base = Date.parse(T1);
    let n = 0;
    const fetchAfter = jest.fn().mockImplementation(() => {
      n += 1;
      const ts = new Date(base + n * 60_000).toISOString(); // strictement croissant
      return Promise.resolve(page([msg(`m${n}`, ts)], true));
    });
    await runDeltaSync({
      getMessages: () => [msg('m1', T1)],
      applyFresh: jest.fn(),
      fetchAfter,
      maxPages: 3,
    });
    expect(fetchAfter).toHaveBeenCalledTimes(3);
    expect(DELTA_SYNC_MAX_PAGES).toBe(20);
  });
});
