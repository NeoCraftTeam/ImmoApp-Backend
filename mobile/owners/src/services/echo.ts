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

interface PusherClient {
  subscribe: (channel: string) => PusherChannel;
  unsubscribe: (channel: string) => void;
  disconnect: () => void;
}

const APP_KEY = process.env.EXPO_PUBLIC_REVERB_APP_KEY;
const HOST = process.env.EXPO_PUBLIC_REVERB_HOST;
const PORT = process.env.EXPO_PUBLIC_REVERB_PORT;
const SCHEME = process.env.EXPO_PUBLIC_REVERB_SCHEME ?? 'https';

let instance: PusherClient | null = null;

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

  let Pusher: new (key: string, opts: Record<string, unknown>) => PusherClient;
  try {
    Pusher = require('pusher-js/react-native').default;
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
