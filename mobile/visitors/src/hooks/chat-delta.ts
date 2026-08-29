/**
 * Chat delta-sync — cœur pur (façon WhatsApp Web).
 *
 * L'historique en cache reste à l'écran ; on ne tire QUE les messages créés
 * après le dernier déjà détenu, borné sur son horodatage UTC (indépendant du
 * fuseau). Les nouveaux messages sont ajoutés en place avec dédoublonnage par
 * uuid — jamais un rechargement complet de la première page.
 *
 * Cette logique est isolée du hook (react-query / AppState / Reverb) pour être
 * testable directement en Jest, comme le reste des `select` critiques.
 */
import type { Message } from '@/types/conversation';

/** Préfixe des inserts optimistes locaux (cf. `useSendMessage` → `temp:${Date.now()}`). */
export const OPTIMISTIC_PREFIX = 'temp:';

/** Cap de sécurité sur la boucle de rattrapage delta (200 msg/page → 4000 messages). */
export const DELTA_SYNC_MAX_PAGES = 20;

/**
 * Un message est « en attente » (non settled) tant qu'il est optimiste local :
 * flag `is_optimistic` OU uuid `temp:*`. Son horodatage local ne doit jamais
 * servir de borne serveur ni être écrasé par un merge delta.
 */
export function isOptimistic(m: Message): boolean {
  return (
    m.is_optimistic === true ||
    (typeof m.uuid === 'string' && m.uuid.startsWith(OPTIMISTIC_PREFIX))
  );
}

/**
 * Curseur delta = `created_at` (UTC) du dernier message SETTLED (non-optimiste)
 * de la liste ascendante. On ignore les optimistes en fin de liste. `null` si la
 * liste ne contient aucun message settled (rien contre quoi delta).
 */
export function latestSettledCursor(list: Message[]): string | null {
  for (let i = list.length - 1; i >= 0; i--) {
    const m = list[i];
    if (!m || isOptimistic(m)) {
      continue;
    }
    return m.created_at ?? null;
  }
  return null;
}

/**
 * Fusionne la première page fraîche (déjà remise en ASCENDANT par l'appelant)
 * avec le cache existant : on garde la traîne plus ancienne déjà chargée, on
 * remplace la fenêtre récente par la page fraîche, et on laisse les envois
 * optimistes en dernier. Sur cache vide → renvoie simplement la page fraîche.
 */
export function mergeFreshPage(
  prev: Message[],
  freshPageAscending: Message[],
): Message[] {
  const freshIds = new Set(freshPageAscending.map((m) => m.uuid));
  const optimisticPending = prev.filter((m) => isOptimistic(m));
  const olderTail = prev.filter(
    (m) => !freshIds.has(m.uuid) && !isOptimistic(m),
  );
  return [...olderTail, ...freshPageAscending, ...optimisticPending];
}

/**
 * Applique un lot delta (oldest-first, strictement postérieur à la traîne
 * settled) : dédoublonnage par uuid, append en place, optimistes en dernier
 * (miroir de l'ordre de `mergeFreshPage`). Renvoie la liste inchangée si le lot
 * n'apporte rien de nouveau (borne inclusive `>=` qui re-renvoie le curseur).
 */
export function mergeDelta(list: Message[], fresh: Message[]): Message[] {
  const known = new Set(list.map((m) => m.uuid));
  const incoming = fresh.filter((m) => !known.has(m.uuid));
  if (incoming.length === 0) {
    return list;
  }
  const settled = list.filter((m) => !isOptimistic(m));
  const optimistic = list.filter((m) => isOptimistic(m));
  return [...settled, ...incoming, ...optimistic];
}

export interface DeltaPage {
  data: Message[];
  has_more: boolean;
}

/**
 * Boucle de sync incrémentale. Lit le cache courant, calcule le curseur UTC du
 * dernier message settled, tire `?after`, fusionne, puis suit `has_more` en
 * avançant le curseur (rattrapage longue absence). No-op si le cache est vide
 * (nouvel appareil → la première page complète du `useQuery` s'en charge) ou
 * s'il n'existe aucun message settled. Un curseur qui n'avance plus stoppe la
 * boucle (garde anti-boucle infinie sur borne inclusive).
 */
export async function runDeltaSync(opts: {
  getMessages: () => Message[];
  applyFresh: (fresh: Message[]) => void;
  fetchAfter: (afterIso: string) => Promise<DeltaPage>;
  maxPages?: number;
}): Promise<void> {
  const {
    getMessages,
    applyFresh,
    fetchAfter,
    maxPages = DELTA_SYNC_MAX_PAGES,
  } = opts;

  const list = getMessages();
  if (list.length === 0) {
    return;
  }

  const cursor = latestSettledCursor(list);
  if (!cursor) {
    return;
  }
  let after: string = cursor;

  for (let page = 0; page < maxPages; page++) {
    const resp = await fetchAfter(after);
    if (resp.data.length === 0) {
      break;
    }

    let newest: string = after;
    for (const m of resp.data) {
      if (m.created_at > newest) {
        newest = m.created_at;
      }
    }

    applyFresh(resp.data);

    if (!resp.has_more || newest === after) {
      break;
    }
    after = newest;
  }
}
