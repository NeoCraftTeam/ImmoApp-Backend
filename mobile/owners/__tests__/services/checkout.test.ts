/**
 * Tests unitaires pure-logic de `extractTxRef` — la fonction qui lit le
 * tx_ref dans une URL de retour gateway. C'est la **clé** du flow
 * paiement : si on rate l'extraction, le user voit toujours "pending".
 *
 * On ne charge pas `services/checkout.ts` directement car il importe
 * `expo-web-browser` (natif). On duplique la logique extractTxRef ici
 * comme dans les autres tests de shape — c'est intentionnel et c'est
 * documenté dans le commentaire du fichier source.
 */

// Re-implémentation locale strictement identique à `services/checkout.ts`.
function extractTxRef(url: string): string | null {
  // Approche manuelle (pas de dep sur expo-linking en test)
  const m = url.match(/[?&](?:tx_ref|txRef)=([^&]+)/);
  return m && m[1] ? decodeURIComponent(m[1]) : null;
}

describe('extractTxRef', () => {
  it('extrait depuis un deep-link standard', () => {
    expect(
      extractTxRef('keyhomeowners://payment-success?tx_ref=KH-ABC-123'),
    ).toBe('KH-ABC-123');
  });

  it('extrait depuis un HTTPS callback', () => {
    expect(
      extractTxRef('https://keyhome.app/payment-success?tx_ref=TX_999&foo=bar'),
    ).toBe('TX_999');
  });

  it('supporte la variante camelCase txRef', () => {
    expect(
      extractTxRef('keyhomeowners://payment-success?txRef=ABC'),
    ).toBe('ABC');
  });

  it('décode les caractères URL-encoded', () => {
    expect(
      extractTxRef('keyhomeowners://payment-success?tx_ref=KH%2FA%2BB'),
    ).toBe('KH/A+B');
  });

  it('renvoie null si tx_ref absent', () => {
    expect(extractTxRef('keyhomeowners://payment-success')).toBeNull();
    expect(extractTxRef('https://example.com?foo=bar')).toBeNull();
  });

  it('renvoie null pour une string vide', () => {
    expect(extractTxRef('')).toBeNull();
  });

  it('ignore les autres paramètres', () => {
    expect(
      extractTxRef('keyhomeowners://x?other=1&tx_ref=ONE&trailing=2'),
    ).toBe('ONE');
  });
});
