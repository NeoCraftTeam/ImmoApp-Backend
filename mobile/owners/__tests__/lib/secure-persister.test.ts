/* global jest, describe, it, expect, beforeEach, afterEach */

const mockAsyncStorage = new Map<string, string>();
const mockSecureStore = new Map<string, string>();

jest.mock('@react-native-async-storage/async-storage', () => ({
  getItem: jest.fn(async (k: string) => mockAsyncStorage.get(k) ?? null),
  setItem: jest.fn(async (k: string, v: string) => {
    mockAsyncStorage.set(k, v);
  }),
  removeItem: jest.fn(async (k: string) => {
    mockAsyncStorage.delete(k);
  }),
}));

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (k: string) => mockSecureStore.get(k) ?? null),
  setItemAsync: jest.fn(async (k: string, v: string) => {
    mockSecureStore.set(k, v);
  }),
  WHEN_UNLOCKED_THIS_DEVICE_ONLY: 1,
}));

jest.mock('expo-crypto', () => ({
  getRandomBytesAsync: jest.fn(async (n: number) => new Uint8Array(n).fill(7)),
}));

import AsyncStorage from '@react-native-async-storage/async-storage';

import { createEncryptedPersister } from '@/lib/secure-persister';

const STORAGE_KEY = 'test-cache';

const sampleClient = {
  buster: 'kh-test',
  timestamp: 123,
  clientState: {
    mutations: [],
    queries: [{ queryKey: ['owner-conversation-messages', 'uuid-1'], body: 'contenu-secret-du-message' }],
  },
};

describe('secure-persister (cache chat chiffré, modèle WhatsApp)', () => {
  beforeEach(() => {
    mockAsyncStorage.clear();
    mockSecureStore.clear();
    jest.clearAllMocks();
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('chiffre le cache au repos et le restaure à l’identique', async () => {
    const p = await createEncryptedPersister(STORAGE_KEY);

    p.persistClient(sampleClient as never);
    await jest.advanceTimersByTimeAsync(1100);

    const raw = await AsyncStorage.getItem(STORAGE_KEY);
    expect(raw).toBeTruthy();
    // Le contenu du chat ne doit JAMAIS apparaître en clair sur le disque.
    expect(raw).not.toContain('contenu-secret-du-message');
    expect(raw).not.toContain('owner-conversation-messages');

    const restored = await p.restoreClient();
    expect(restored).toEqual(sampleClient);
  });

  it('purge un cache legacy en clair et repart proprement', async () => {
    await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify({ plain: true }));

    const p = await createEncryptedPersister(STORAGE_KEY);
    const restored = await p.restoreClient();

    expect(restored).toBeUndefined();
    expect(await AsyncStorage.getItem(STORAGE_KEY)).toBeNull();
  });

  it('retourne undefined sans cache existant', async () => {
    const p = await createEncryptedPersister(STORAGE_KEY);
    expect(await p.restoreClient()).toBeUndefined();
  });

  it('crée la clé AES une seule fois puis la réutilise', async () => {
    const SecureStore = jest.requireMock('expo-secure-store') as {
      setItemAsync: jest.Mock;
    };

    await createEncryptedPersister('a');
    await createEncryptedPersister('b');

    expect(SecureStore.setItemAsync).toHaveBeenCalledTimes(1);

    const p1 = await createEncryptedPersister('shared');
    p1.persistClient(sampleClient as never);
    await jest.advanceTimersByTimeAsync(1100);
    const p2 = await createEncryptedPersister('shared');
    expect(await p2.restoreClient()).toEqual(sampleClient);
  });

  it('throttle les écritures : un burst ne produit qu’un seul snapshot', async () => {
    const p = await createEncryptedPersister(STORAGE_KEY);

    p.persistClient({ ...sampleClient, timestamp: 1 } as never);
    p.persistClient({ ...sampleClient, timestamp: 2 } as never);
    p.persistClient({ ...sampleClient, timestamp: 3 } as never);
    await jest.advanceTimersByTimeAsync(1100);

    const restored = (await p.restoreClient()) as { timestamp: number };
    expect(restored.timestamp).toBe(3);
  });
});
