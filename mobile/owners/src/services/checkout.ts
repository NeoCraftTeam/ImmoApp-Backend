import * as WebBrowser from 'expo-web-browser';
import * as Linking from 'expo-linking';

import { reportError, trackEvent } from '@/services/monitoring';

/**
 * Bridge UX entre l'app et la page hosted-checkout du gateway
 * (GeniusPay pour mobile money, Stripe Checkout pour les cartes).
 *
 *  1. On reçoit du backend `payment_link` (URL HTTPS).
 *  2. On ouvre `WebBrowser.openAuthSessionAsync` avec un return URL
 *     `keyhome://payment-success?tx_ref={tx_ref}` — ce schéma est
 *     déclaré dans `app.json` (`scheme: "keyhome"`).
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
 * (`payment.native-return`) qu'il passe à la passerelle comme success_url —
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
 * jusqu'à fermeture (succès/cancel). En cas d'environnement où
 * `openAuthSessionAsync` n'est pas dispo (rare), fallback sur
 * `openBrowserAsync` non-bloquant — le caller doit alors compter sur
 * le deep-link Linking + AppState pour détecter le retour.
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
      const extracted = extractTxRef(result.url) ?? txRef;
      trackEvent('payment.checkout.success', { gateway_url_host: safeHost(result.url) });
      return { txRef: extracted, cancelled: false, rawUrl: result.url };
    }
    if (result.type === 'cancel' || result.type === 'dismiss') {
      trackEvent('payment.checkout.cancelled');
      return { txRef: null, cancelled: true };
    }
    return { txRef: null, cancelled: false };
  } catch (err) {
    reportError(err, { txRef });
    return { txRef: null, cancelled: false, error: err as Error };
  }
}

/** Lit `tx_ref` ou `txRef` dans une URL. */
export function extractTxRef(url: string): string | null {
  try {
    const parsed = Linking.parse(url);
    const qp = parsed.queryParams ?? {};
    const ref = qp.tx_ref ?? qp.txRef;
    if (typeof ref === 'string' && ref.length > 0) return ref;
    if (Array.isArray(ref) && typeof ref[0] === 'string') return ref[0];
    return null;
  } catch {
    // Fallback parsing manuel — utile pour des URLs custom-scheme
    const m = url.match(/[?&](?:tx_ref|txRef)=([^&]+)/);
    return m && m[1] ? decodeURIComponent(m[1]) : null;
  }
}

function safeHost(url: string): string {
  try {
    return new URL(url).host;
  } catch {
    return 'unknown';
  }
}
