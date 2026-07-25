import type { PaymentTransaction } from '@/types/payment';

const TYPE_LABELS: Record<string, string> = {
  credit: 'Crédits',
  unlock: 'Déblocage',
  subscription: 'Abonnement',
  boost: 'Boost',
};

function normalizeStatus(value: string): PaymentTransaction['status'] {
  if (value === 'succeeded') {
    return 'success';
  }

  return value;
}

/**
 * Normalise les lignes `PaymentResource` pour l'historique mobile visiteur.
 */
export function normalizePaymentHistoryEntry(
  raw: Record<string, unknown>,
): PaymentTransaction {
  const type = String(raw.type ?? '');
  const packName = typeof raw.pack_name === 'string' ? raw.pack_name : null;
  const reference = String(raw.reference ?? raw.tx_ref ?? '');

  return {
    id: String(raw.id ?? ''),
    amount: Number(raw.amount ?? 0),
    currency: typeof raw.currency === 'string' ? raw.currency : 'XAF',
    status: normalizeStatus(String(raw.status ?? 'pending')),
    status_label: typeof raw.status_label === 'string' ? raw.status_label : undefined,
    provider:
      typeof raw.payment_method_label === 'string'
        ? raw.payment_method_label
        : typeof raw.gateway_label === 'string'
          ? raw.gateway_label
          : 'KeyHome',
    payment_method_detail:
      typeof raw.payment_method_detail === 'string' ? raw.payment_method_detail : null,
    reference: reference || null,
    description: packName ?? TYPE_LABELS[type] ?? (type || null),
    type,
    created_at: String(raw.created_at ?? ''),
  };
}

export function normalizePaymentHistoryList(
  payload: { data?: unknown[] },
): PaymentTransaction[] {
  const rows = Array.isArray(payload.data) ? payload.data : [];

  return rows.map((row) =>
    normalizePaymentHistoryEntry(row as Record<string, unknown>),
  );
}
