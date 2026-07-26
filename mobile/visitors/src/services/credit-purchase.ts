import AsyncStorage from '@react-native-async-storage/async-storage';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';

export type PaymentOutcome = 'completed' | 'failed' | 'pending';

/**
 * Confirme un achat en RÉCONCILIANT ACTIVEMENT avec la passerelle via
 * POST /credits/verify-purchase (syncPaymentStatus re-query Kpay/Stripe).
 * Contrairement au webhook, ça fonctionne même quand le webhook ne peut
 * pas joindre le backend (local/sandbox). Codes : 200 = completed,
 * 202 = pending, 422 = failed.
 *
 * La fenêtre réelle dépend des paramètres passés par l'appelant
 * (attempts × intervalMs) ; l'expiration retourne toujours `pending`,
 * jamais un faux « échec » — le webhook reste la source de vérité.
 */
export async function pollVerifyPurchase(
  lookupRef: string,
  {
    attempts = 8,
    intervalMs = 1500,
    gatewayRedirectStatus,
  }: {
    attempts?: number;
    intervalMs?: number;
    gatewayRedirectStatus?: string | null;
  } = {},
): Promise<PaymentOutcome> {
  const payload: Record<string, string> =
    lookupRef.startsWith('KH-') ? { tx_ref: lookupRef } : { reference: lookupRef };

  if (gatewayRedirectStatus) {
    payload.gateway_redirect_status = gatewayRedirectStatus;
  }

  for (let i = 0; i < attempts; i++) {
    try {
      const { data } = await apiClient.post<{ status?: string }>(
        ENDPOINTS.credits.verifyPurchase,
        payload,
      );
      if (data?.status === 'completed') {
        return 'completed';
      }
      if (data?.status === 'failed') {
        return 'failed';
      }
      // 'pending' / 'not_found' → on retente.
    } catch (err) {
      // 422 = paiement échoué (verify renvoie status:failed en erreur).
      const status = (err as { response?: { data?: { status?: string } } })?.response?.data?.status;
      if (status === 'failed') {
        return 'failed';
      }
      /* autres erreurs transitoires → on retente */
    }
    await new Promise((r) => setTimeout(r, intervalMs));
  }
  return 'pending';
}

export interface PendingCreditPurchase {
  txRef: string;
  packageId: string;
  credits: number;
  startedAt: number;
}

const PENDING_PURCHASE_KEY = 'kh_pending_credit_purchase_v1';
/** Au-delà de 24 h, le cleanup backend a tranché — inutile de relancer. */
const PENDING_PURCHASE_MAX_AGE_MS = 24 * 60 * 60 * 1000;

/**
 * Persiste le tx_ref d'un achat AVANT d'ouvrir le checkout : si iOS tue
 * l'app pendant la validation mobile money, l'achat reste réconciliable
 * au prochain lancement (écran crédits ou deep-link credits/callback).
 */
export async function savePendingCreditPurchase(entry: PendingCreditPurchase): Promise<void> {
  try {
    await AsyncStorage.setItem(PENDING_PURCHASE_KEY, JSON.stringify(entry));
  } catch {
    /* stockage best-effort — le webhook créditera de toute façon */
  }
}

export async function loadPendingCreditPurchase(): Promise<PendingCreditPurchase | null> {
  try {
    const raw = await AsyncStorage.getItem(PENDING_PURCHASE_KEY);
    if (!raw) {
      return null;
    }
    const parsed = JSON.parse(raw) as Partial<PendingCreditPurchase>;
    if (typeof parsed.txRef !== 'string' || parsed.txRef === '') {
      await clearPendingCreditPurchase();
      return null;
    }
    const startedAt = typeof parsed.startedAt === 'number' ? parsed.startedAt : 0;
    if (Date.now() - startedAt > PENDING_PURCHASE_MAX_AGE_MS) {
      await clearPendingCreditPurchase();
      return null;
    }
    return {
      txRef: parsed.txRef,
      packageId: typeof parsed.packageId === 'string' ? parsed.packageId : '',
      credits: typeof parsed.credits === 'number' ? parsed.credits : 0,
      startedAt,
    };
  } catch {
    return null;
  }
}

export async function clearPendingCreditPurchase(): Promise<void> {
  try {
    await AsyncStorage.removeItem(PENDING_PURCHASE_KEY);
  } catch {
    /* ignore */
  }
}
