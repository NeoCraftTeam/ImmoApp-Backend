import * as WebBrowser from 'expo-web-browser';
import * as Linking from 'expo-linking';

import { reportError, trackEvent } from '@/services/monitoring';

/**
 * Bridge UX entre l'app et la page hosted-checkout du gateway
 * (Kpay pour mobile money, Stripe Checkout pour les cartes).
 *
 *  1. On reçoit du backend `payment_link` (URL HTTPS).
 *  2. On ouvre `WebBrowser.openAuthSessionAsync` avec un return URL
 *     `keyhomeowners://payment-success?tx_ref={tx_ref}` — ce schéma est
 *     déclaré dans `app.json` (`scheme: "keyhomeowners"`).
 *  3. Le gateway redirige vers ce return URL après succès/échec/abandon.
 *  4. Le browser session se ferme et nous renvoie `result.url` — on en
 *     extrait `tx_ref` pour ensuite naviguer vers `/payment-success`.
 *
 * Renvoie :
 *  - `txRef` extrait → l'appelant peut polling
 *  - `cancelled` = true si l'utilisateur a fermé le browser
 *  - `error` quelconque autre
 */

export interface CheckoutResult {
  txRef: string | null;
  reference: string | null;
  paymentId: string | null;
  status: string | null;
  cancelled: boolean;
  rawUrl?: string;
  error?: Error;
}

const SCHEME = 'keyhomeowners';
const RETURN_PATH = '/payment-success';

/** Construit l'URL de retour deep-link à passer au gateway. */
export function buildReturnUrl(txRef: string): string {
  return Linking.createURL(RETURN_PATH, {
    scheme: SCHEME,
    queryParams: { tx_ref: txRef },
  });
}

/**
 * Deep-link de retour SANS tx_ref, à envoyer au backend comme `callback_url`.
 *
 * Le backend valide ce schéma puis l'enveloppe dans un pont HTTPS
 * (`payment.native-return`) qu'il passe à la passerelle comme returnUrl —
 * et y appose lui-même le `tx_ref`. En fin de paiement, la passerelle atteint
 * le pont, qui renvoie un 302 vers ce deep-link : l'onglet in-app se ferme
 * nativement et l'app reprend la main. Sans ce callback, la passerelle
 * redirige vers la page web de retour et l'onglet ne se ferme jamais.
 */
export function buildCallbackUrl(): string {
  return Linking.createURL(RETURN_PATH, { scheme: SCHEME });
}

/**
 * Ouvre le hosted-checkout dans un WebBrowser auth-session. Bloque
 * jusqu'à fermeture (succès/cancel). Si `openAuthSessionAsync` échoue
 * (rare), on retourne `error` — la route expo-router `/payment-success`
 * couvre de toute façon le deep-link direct (cold start compris).
 */
export async function openHostedCheckout(
  paymentLink: string,
  txRef: string,
): Promise<CheckoutResult> {
  const returnUrl = buildReturnUrl(txRef);
  trackEvent('payment.checkout.open', { gateway_url_host: safeHost(paymentLink) });

  try {
    const result = await WebBrowser.openAuthSessionAsync(paymentLink, returnUrl, {
      showInRecents: false,
      // Session isolée → iOS n'affiche pas le prompt de consentement
      // « … souhaite utiliser … pour se connecter ». Inutile pour un paiement.
      preferEphemeralSession: true,
    });

    if (result.type === 'success' && result.url) {
      const extracted = extractPaymentReturnParams(result.url);
      trackEvent('payment.checkout.success', { gateway_url_host: safeHost(result.url) });
      return {
        txRef: extracted.txRef ?? txRef,
        reference: extracted.reference,
        paymentId: extracted.paymentId,
        status: extracted.status,
        cancelled: false,
        rawUrl: result.url,
      };
    }
    if (result.type === 'cancel' || result.type === 'dismiss') {
      trackEvent('payment.checkout.cancelled');
      return { txRef: null, reference: null, paymentId: null, status: null, cancelled: true };
    }
    return { txRef: null, reference: null, paymentId: null, status: null, cancelled: false };
  } catch (err) {
    reportError(err, { txRef });
    return {
      txRef: null,
      reference: null,
      paymentId: null,
      status: null,
      cancelled: false,
      error: err as Error,
    };
  }
}

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

/** Lit `tx_ref` ou `txRef` dans une URL. */
export function extractTxRef(url: string): string | null {
  return extractPaymentReturnParams(url).txRef;
}

function safeHost(url: string): string {
  try {
    return new URL(url).host;
  } catch {
    return 'unknown';
  }
}
