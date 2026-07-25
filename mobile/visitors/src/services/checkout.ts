import * as Linking from 'expo-linking';

/** Lit les paramètres de retour Kpay dans une URL (deep-link ou pont HTTPS). */
export function extractPaymentReturnParams(url: string): {
  txRef: string | null;
  reference: string | null;
  paymentId: string | null;
  status: string | null;
} {
  try {
    const parsed = Linking.parse(url);
    const qp = parsed.queryParams ?? {};
    const read = (key: string): string | null => {
      const ref = qp[key];
      if (typeof ref === 'string' && ref.length > 0) {
        return ref;
      }
      if (Array.isArray(ref) && typeof ref[0] === 'string') {
        return ref[0];
      }
      return null;
    };

    return {
      txRef: read('tx_ref') ?? read('txRef'),
      reference: read('reference'),
      paymentId: read('paymentId'),
      status: read('status'),
    };
  } catch {
    const txMatch = url.match(/[?&](?:tx_ref|txRef)=([^&]+)/);
    const refMatch = url.match(/[?&]reference=([^&]+)/);
    const payMatch = url.match(/[?&]paymentId=([^&]+)/);
    const statusMatch = url.match(/[?&]status=([^&]+)/);
    return {
      txRef: txMatch?.[1] ? decodeURIComponent(txMatch[1]) : null,
      reference: refMatch?.[1] ? decodeURIComponent(refMatch[1]) : null,
      paymentId: payMatch?.[1] ? decodeURIComponent(payMatch[1]) : null,
      status: statusMatch?.[1] ? decodeURIComponent(statusMatch[1]) : null,
    };
  }
}

/** Identifiant utilisable pour verify / public-status (KH-* ou référence gateway). */
export function resolvePaymentLookupRef(
  txRef: string | null | undefined,
  reference: string | null | undefined,
  paymentId: string | null | undefined,
): string | null {
  if (typeof txRef === 'string' && txRef.length > 0) {
    return txRef;
  }
  if (typeof reference === 'string' && reference.length > 0) {
    return reference;
  }
  if (typeof paymentId === 'string' && paymentId.length > 0) {
    return paymentId;
  }

  return null;
}
