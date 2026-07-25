import Pusher from 'pusher-js/react-native';

import { apiClient } from '@/api/client';

/**
 * Adaptateur Laravel Echo / Reverb sur `pusher-js/react-native`.
 *
 * Reverb implémente le protocole Pusher → on utilise le client
 * pusher-js standard mais pointé sur l'hôte Reverb du backend.
 *
 * Variables d'env attendues (toutes optionnelles — sans elles, on
 * fallback gracieusement sur le polling existant) :
 *   - `EXPO_PUBLIC_REVERB_APP_KEY`      clé publique
 *   - `EXPO_PUBLIC_REVERB_HOST`         hôte (ex: `reverb.keyhome.app`)
 *   - `EXPO_PUBLIC_REVERB_PORT`         port (443 prod, 8080 local)
 *   - `EXPO_PUBLIC_REVERB_SCHEME`       `https` | `http`
 *
 * L'auth des channels privés passe par `POST /broadcasting/auth`
 * avec le bearer Sanctum — apiClient le fait automatiquement via son
 * interceptor.
 */

const APP_KEY = process.env.EXPO_PUBLIC_REVERB_APP_KEY;
const HOST = process.env.EXPO_PUBLIC_REVERB_HOST;
const PORT = process.env.EXPO_PUBLIC_REVERB_PORT;
const SCHEME = process.env.EXPO_PUBLIC_REVERB_SCHEME ?? 'https';

let instance: Pusher | null = null;

/**
 * Vrai quand les env vars Reverb sont présentes — les hooks temps réel
 * s'en servent pour distinguer « connecté en WS » de « fallback polling »
 * (avant, `isConnected` passait à true même sans aucune config).
 */
export function isEchoConfigured(): boolean {
  return Boolean(APP_KEY && HOST);
}

/**
 * Retourne (et crée à la demande) l'instance Pusher globale.
 * Renvoie `null` si la config Reverb est absente — le caller doit
 * gérer ce cas (fallback polling).
 */
export function getEchoClient(): Pusher | null {
  if (!APP_KEY || !HOST) return null;
  if (instance) return instance;

  const portNum = PORT ? Number(PORT) : SCHEME === 'https' ? 443 : 8080;
  instance = new Pusher(APP_KEY, {
    wsHost: HOST,
    wsPort: portNum,
    wssPort: portNum,
    forceTLS: SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    cluster: 'mt1', // ignoré quand wsHost est défini, mais requis par le typing
    authorizer: (channel) => ({
      authorize: (socketId, callback) => {
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

/**
 * Coupe la connexion (au signOut, par exemple). Idempotent — appelable
 * plusieurs fois sans risque.
 */
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
 * Subscribe à un channel privé Laravel Echo (`private-{name}`) et
 * appelle `onEvent(eventName, payload)` pour chaque message reçu.
 *
 * Renvoie une fonction de cleanup à appeler dans un `useEffect` return.
 */
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
