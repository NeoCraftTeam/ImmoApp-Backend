import AsyncStorage from '@react-native-async-storage/async-storage';
import type { PersistedClient, Persister } from '@tanstack/react-query-persist-client';
import { AES, Hex, Utf8, type WordArray } from 'crypto-es';
import * as Crypto from 'expo-crypto';
import * as SecureStore from 'expo-secure-store';

/**
 * Persister TanStack Query CHIFFRÉ — modèle WhatsApp : le cache (fil
 * de discussion, inbox…) est stocké sur l'appareil chiffré en AES-256,
 * la clé ne quitte jamais le SecureStore (Keystore Android / Keychain
 * iOS, `THIS_DEVICE_ONLY` — pas de sync iCloud, pas d'export backup).
 *
 * Au cold-start, l'UI se réhydrate depuis ce cache chiffré → affichage
 * INSTANTANÉ des conversations et des messages, puis TanStack Query
 * resynchronise en arrière-plan (stale-while-revalidate), exactement
 * comme WhatsApp.
 *
 * Le format AES d'crypto-es embarque un IV aléatoire par écriture
 * (compatible OpenSSL) — deux snapshots identiques donnent deux
 * ciphertexts différents.
 */

const SECURE_KEY_ID = 'kh_query_cache_key_v1';

function bytesToHex(bytes: Uint8Array): string {
  return Array.from(bytes)
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
}

/**
 * Charge la clé AES-256 du cache, la crée au premier lancement (32
 * octets aléatoires). Stockée en hex dans le SecureStore.
 */
async function getOrCreateCacheKey(): Promise<WordArray> {
  try {
    const existing = await SecureStore.getItemAsync(SECURE_KEY_ID);
    if (existing && existing.length === 64) {
      return Hex.parse(existing);
    }
  } catch {
    // SecureStore indisponible (rare) — on régénère : l'ancien cache
    // deviendra illisible et sera purgé au restore (fail-safe).
  }

  const bytes = await Crypto.getRandomBytesAsync(32);
  const hex = bytesToHex(bytes);
  try {
    await SecureStore.setItemAsync(SECURE_KEY_ID, hex, {
      keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
    });
  } catch {
    /* best-effort — la clé vit alors en mémoire pour cette session */
  }
  return Hex.parse(hex);
}

/**
 * Crée le `Persister` chiffré pour la clé de stockage donnée.
 * Async (SecureStore) — le QueryProvider attend cette résolution avant
 * de monter (couvert par le splash natif, ~10-30 ms une seule fois).
 */
export async function createEncryptedPersister(storageKey: string): Promise<Persister> {
  const key = await getOrCreateCacheKey();

  // Throttle trailing-edge 1 s (comme l'ancien createAsyncStoragePersister) :
  // les bursts d'updates (thread actif, realtime) ne déclenchent qu'une
  // écriture chiffrée — chiffrer à chaque notify serait du gaspillage.
  let pending: PersistedClient | null = null;
  let timer: ReturnType<typeof setTimeout> | null = null;

  const flush = (): void => {
    timer = null;
    const toWrite = pending;
    pending = null;
    if (!toWrite) return;
    void (async () => {
      try {
        const cipher = AES.encrypt(JSON.stringify(toWrite), key).toString();
        await AsyncStorage.setItem(storageKey, cipher);
      } catch {
        /* best-effort — la persistance ne doit jamais casser l'app */
      }
    })();
  };

  return {
    persistClient: (client) => {
      pending = client;
      if (!timer) {
        timer = setTimeout(flush, 1000);
      }
      return Promise.resolve();
    },

    restoreClient: async () => {
      try {
        const raw = await AsyncStorage.getItem(storageKey);
        if (!raw) return undefined;
        const plain = AES.decrypt(raw, key).toString(Utf8);
        if (!plain) throw new Error('decrypt produced empty payload');
        return JSON.parse(plain) as PersistedClient;
      } catch {
        // Cache legacy en clair (avant chiffrement) ou corrompu → purge
        // et démarrage propre, l'API refera foi.
        await AsyncStorage.removeItem(storageKey).catch(() => {});
        return undefined;
      }
    },

    removeClient: async () => {
      await AsyncStorage.removeItem(storageKey).catch(() => {});
    },
  };
}
