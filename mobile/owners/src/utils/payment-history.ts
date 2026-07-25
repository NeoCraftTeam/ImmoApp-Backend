import type { PaymentEntry, PaymentStatus } from '@/types/payment';

const TYPE_LABELS: Record<string, string> = {
  credit: 'Crédits',
  unlock: 'Déblocage',
  subscription: 'Abonnement',
  boost: 'Boost',
};

function normalizeStatus(value: string): PaymentStatus {
  if (value === 'succeeded') {
    return 'success';
  }
  if (
    value === 'pending'
    || value === 'success'
    || value === 'failed'
    || value === 'refunded'
    || value === 'cancelled'
  ) {
    return value;
  }

  return 'pending';
}

/**
 * Maps `PaymentResource` rows from `GET /payments/history` to the
 * owner-app `PaymentEntry` shape (`reference` → `tx_ref`, labels, title).
 */
export function normalizePaymentHistoryEntry(
  raw: Record<string, unknown>,
): PaymentEntry {
  const type = String(raw.type ?? '');
  const packName = typeof raw.pack_name === 'string' ? raw.pack_name : null;
  const reference = String(raw.reference ?? raw.tx_ref ?? '');

  return {
    id: String(raw.id ?? ''),
    tx_ref: reference,
    amount: Number(raw.amount ?? 0),
    currency: typeof raw.currency === 'string' ? raw.currency : undefined,
    status: normalizeStatus(String(raw.status ?? 'pending')),
    status_label: typeof raw.status_label === 'string' ? raw.status_label : undefined,
    description: packName ?? TYPE_LABELS[type] ?? (type || null),
    method: typeof raw.payment_method === 'string' ? raw.payment_method : null,
    payment_method_label:
      typeof raw.payment_method_label === 'string' ? raw.payment_method_label : null,
    payment_method_detail:
      typeof raw.payment_method_detail === 'string' ? raw.payment_method_detail : null,
    created_at: String(raw.created_at ?? ''),
    reference_type: typeof raw.reference_type === 'string' ? raw.reference_type : null,
    reference_id: typeof raw.reference_id === 'string' ? raw.reference_id : null,
  };
}

export function normalizePaymentHistoryPayload<T extends { data?: unknown[] }>(
  payload: T,
): T & { data: PaymentEntry[] } {
  const rows = Array.isArray(payload.data) ? payload.data : [];

  return {
    ...payload,
    data: rows.map((row) =>
      normalizePaymentHistoryEntry(row as Record<string, unknown>),
    ),
  };
}
