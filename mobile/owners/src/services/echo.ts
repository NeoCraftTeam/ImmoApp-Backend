import { apiClient } from '@/api/client';

/**
 * Adaptateur Laravel Echo / Reverb pour l'app owner. `pusher-js` est
 * lazy-required parce qu'il n'est PAS dans les deps par défaut — la
 * realtime layer reste optionnelle : sans le package (ou sans la config
 * Reverb dans l'env), tout l'app fonctionne en mode polling. Dès que le
 * package est installé + les env vars sont présentes, ça bascule en
 * websocket automatiquement.
 */

interface PusherChannel {
  bind: (event: string, cb: (data: unknown) => void) => void;
  unbind: (event: string) => void;
}

interface PusherConnection {
  bind: (event: string, cb: (data?: unknown) => void) => void;
  unbind: (event: string, cb: (data?: unknown) => void) => void;
  state?: string;
}

interface PusherClient {
  subscribe: (channel: string) => PusherChannel;
  unsubscribe: (channel: string) => void;
  disconnect: () => void;
  connection: PusherConnection;
}

const APP_KEY = process.env.EXPO_PUBLIC_REVERB_APP_KEY;
const HOST = process.env.EXPO_PUBLIC_REVERB_HOST;
const PORT = process.env.EXPO_PUBLIC_REVERB_PORT;
const SCHEME = process.env.EXPO_PUBLIC_REVERB_SCHEME ?? 'https';

let instance: PusherClient | null = null;

type PusherCtor = new (key: string, opts: Record<string, unknown>) => PusherClient;

/**
 * Résout le constructeur Pusher quel que soit le format du bundle.
 * Le dist react-native de pusher-js 8.5+ exporte `module.exports.Pusher`
 * (export nommé) — lire `.default` renvoyait `undefined` et le
 * `new Pusher()` en aval jetait « constructor is not callable ». On teste
 * toutes les formes connues et on renvoie null (repli polling) plutôt que
 * de crasher l'app au montage.
 */
function resolvePusherCtor(mod: unknown): PusherCtor | null {
  const m = mod as Record<string, unknown> | null;
  const candidates: unknown[] = [
    mod,
    m?.default,
    m?.Pusher,
    (m?.Pusher as Record<string, unknown> | undefined)?.default,
    (m?.default as Record<string, unknown> | undefined)?.default,
  ];
  for (const candidate of candidates) {
    if (typeof candidate === 'function') {
      return candidate as PusherCtor;
    }
  }
  return null;
}

/** Vrai quand Reverb est configuré ET que `pusher-js` est installé. */
export function isEchoConfigured(): boolean {
  if (!APP_KEY || !HOST) return false;
  try {
    require('pusher-js/react-native');
    return true;
  } catch {
    return false;
  }
}

export function getEchoClient(): PusherClient | null {
  if (!APP_KEY || !HOST) return null;
  if (instance) return instance;

  let Pusher: PusherCtor;
  try {
    const resolved = resolvePusherCtor(require('pusher-js/react-native'));
    if (!resolved) {
      if (__DEV__) {
        console.warn('[echo] constructeur Pusher introuvable dans pusher-js/react-native — repli polling');
      }
      return null;
    }
    Pusher = resolved;
  } catch (err) {
    // Le package n'est pas installe. Cas attendu pour les dev qui
    // n'ont pas configure Reverb — on log en DEV pour faciliter
    // le debug et on tombe en silence en prod (un dev devrait avoir
    // configure le realtime AVANT de pusher en prod). Sans ce log,
    // un dev qui essayait d'activer Reverb voyait juste "pas de
    // realtime" sans savoir pourquoi.
    if (__DEV__) {
      console.warn('[echo] pusher-js/react-native non installé — realtime désactivé', err);
    }
    return null;
  }

  const portNum = PORT ? Number(PORT) : SCHEME === 'https' ? 443 : 8080;
  instance = new Pusher(APP_KEY, {
    wsHost: HOST,
    wsPort: portNum,
    wssPort: portNum,
    forceTLS: SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    cluster: 'mt1',
    authorizer: (channel: { name: string }) => ({
      authorize: (
        socketId: string,
        callback: (err: Error | null, data: unknown) => void,
      ) => {
        apiClient
          .post<{ auth: string; channel_data?: string }>('/broadcasting/auth', {
            socket_id: socketId,
            channel_name: channel.name,
          })
          .then(({ data }) => callback(null, data))
          .catch((err) => callback(err as Error, null));
      },
    }),
  });

  return instance;
}

export function disconnectEcho(): void {
  if (!instance) return;
  try {
    instance.disconnect();
  } catch {
    /* ignore */
  }
  instance = null;
}

/**
 * S'abonne à l'état réel du socket Reverb (connected/disconnected).
 * Permet à l'UI d'afficher « En direct » seulement quand le WS est
 * vraiment ouvert — et non juste parce que les env vars sont présentes.
 * Renvoie une fonction de désabonnement ; no-op si Reverb non configuré.
 */
export function onConnectionState(cb: (connected: boolean) => void): () => void {
  const client = getEchoClient();
  if (!client?.connection) return () => {};
  const conn = client.connection;
  const handleConnected = () => cb(true);
  const handleDisconnected = () => cb(false);
  conn.bind('connected', handleConnected);
  conn.bind('disconnected', handleDisconnected);
  conn.bind('unavailable', handleDisconnected);
  conn.bind('failed', handleDisconnected);
  // État courant immédiat (le socket peut déjà être connecté).
  cb(conn.state === 'connected');
  return () => {
    try {
      conn.unbind('connected', handleConnected);
      conn.unbind('disconnected', handleDisconnected);
      conn.unbind('unavailable', handleDisconnected);
      conn.unbind('failed', handleDisconnected);
    } catch {
      /* ignore */
    }
  };
}

export function subscribePrivate(
  channelName: string,
  events: string[],
  onEvent: (event: string, data: unknown) => void,
): () => void {
  const client = getEchoClient();
  if (!client) return () => {};
  const channel = client.subscribe(`private-${channelName}`);
  for (const evt of events) {
    channel.bind(evt, (data: unknown) => onEvent(evt, data));
  }
  return () => {
    try {
      for (const evt of events) {
        channel.unbind(evt);
      }
      client.unsubscribe(`private-${channelName}`);
    } catch {
      /* ignore */
    }
  };
}
