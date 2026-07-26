/**
 * Tests du service de réconciliation d'achat de crédits :
 * - pollVerifyPurchase : mapping 200 completed / 422 failed / expiration → pending
 *   (jamais un faux « échec »), payload tx_ref vs reference.
 * - persistance du tx_ref en cours (reprise après kill de l'app).
 */
jest.mock('@react-native-async-storage/async-storage', () =>
  require('@react-native-async-storage/async-storage/jest/async-storage-mock'),
);

jest.mock('@/api/client', () => ({
  apiClient: { post: jest.fn() },
}));

import AsyncStorage from '@react-native-async-storage/async-storage';

import { apiClient } from '@/api/client';
import {
  clearPendingCreditPurchase,
  loadPendingCreditPurchase,
  pollVerifyPurchase,
  savePendingCreditPurchase,
} from '@/services/credit-purchase';

const mockedPost = apiClient.post as jest.Mock;

beforeEach(() => {
  jest.clearAllMocks();
  return AsyncStorage.clear();
});

describe('pollVerifyPurchase', () => {
  it('renvoie completed dès que le backend confirme', async () => {
    mockedPost.mockResolvedValueOnce({ data: { status: 'completed' } });

    const outcome = await pollVerifyPurchase('KH-TEST-1', { attempts: 3, intervalMs: 1 });

    expect(outcome).toBe('completed');
    expect(mockedPost).toHaveBeenCalledTimes(1);
    expect(mockedPost.mock.calls[0][1]).toEqual({ tx_ref: 'KH-TEST-1' });
  });

  it('utilise reference quand la référence ne commence pas par KH-', async () => {
    mockedPost.mockResolvedValueOnce({ data: { status: 'completed' } });

    await pollVerifyPurchase('KPAY-REF-9', { attempts: 1, intervalMs: 1 });

    expect(mockedPost.mock.calls[0][1]).toEqual({ reference: 'KPAY-REF-9' });
  });

  it('renvoie failed sur un 422 avec status failed', async () => {
    mockedPost.mockRejectedValueOnce({ response: { data: { status: 'failed' } } });

    const outcome = await pollVerifyPurchase('KH-TEST-2', { attempts: 3, intervalMs: 1 });

    expect(outcome).toBe('failed');
    expect(mockedPost).toHaveBeenCalledTimes(1);
  });

  it('renvoie pending (jamais failed) quand la fenêtre expire', async () => {
    mockedPost.mockResolvedValue({ data: { status: 'pending' } });

    const outcome = await pollVerifyPurchase('KH-TEST-3', { attempts: 2, intervalMs: 1 });

    expect(outcome).toBe('pending');
    expect(mockedPost).toHaveBeenCalledTimes(2);
  });

  it('retente sur une erreur réseau transitoire', async () => {
    mockedPost
      .mockRejectedValueOnce(new Error('network'))
      .mockResolvedValueOnce({ data: { status: 'completed' } });

    const outcome = await pollVerifyPurchase('KH-TEST-4', { attempts: 3, intervalMs: 1 });

    expect(outcome).toBe('completed');
  });

  it('transmet gateway_redirect_status quand fourni', async () => {
    mockedPost.mockResolvedValueOnce({ data: { status: 'completed' } });

    await pollVerifyPurchase('KH-TEST-5', {
      attempts: 1,
      intervalMs: 1,
      gatewayRedirectStatus: 'completed',
    });

    expect(mockedPost.mock.calls[0][1]).toEqual({
      tx_ref: 'KH-TEST-5',
      gateway_redirect_status: 'completed',
    });
  });
});

describe('persistance de l’achat en cours', () => {
  it('sauvegarde puis recharge un achat récent', async () => {
    await savePendingCreditPurchase({
      txRef: 'KH-PERSIST-1',
      packageId: 'pkg-1',
      credits: 50,
      startedAt: Date.now(),
    });

    const loaded = await loadPendingCreditPurchase();

    expect(loaded?.txRef).toBe('KH-PERSIST-1');
    expect(loaded?.credits).toBe(50);
  });

  it('purge un achat trop ancien (>24 h)', async () => {
    await savePendingCreditPurchase({
      txRef: 'KH-OLD-1',
      packageId: 'pkg-1',
      credits: 50,
      startedAt: Date.now() - 25 * 60 * 60 * 1000,
    });

    expect(await loadPendingCreditPurchase()).toBeNull();
  });

  it('clear supprime l’entrée', async () => {
    await savePendingCreditPurchase({
      txRef: 'KH-CLEAR-1',
      packageId: 'pkg-1',
      credits: 10,
      startedAt: Date.now(),
    });
    await clearPendingCreditPurchase();

    expect(await loadPendingCreditPurchase()).toBeNull();
  });

  it('ignore une entrée corrompue', async () => {
    await AsyncStorage.setItem('kh_pending_credit_purchase_v1', '{not-json');

    expect(await loadPendingCreditPurchase()).toBeNull();
  });
});
