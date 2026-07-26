/**
 * Tests du module de préservation de route post-login : un deep-link
 * intercepté par l'AuthGate doit être rejoué après connexion, et jamais
 * une route du groupe (auth) ni la racine.
 */
import { consumePendingRoute, rememberPendingRoute } from '@/auth/pending-route';

beforeEach(() => {
  // Draine l'état module entre les tests.
  consumePendingRoute();
});

describe('pending-route', () => {
  it('mémorise puis consomme une route avec query', () => {
    rememberPendingRoute('/payment-success?tx_ref=KH-ABC123');

    expect(consumePendingRoute()).toBe('/payment-success?tx_ref=KH-ABC123');
    expect(consumePendingRoute()).toBeNull();
  });

  it('la dernière route mémorisée gagne', () => {
    rememberPendingRoute('/payments');
    rememberPendingRoute('/messages/42');

    expect(consumePendingRoute()).toBe('/messages/42');
  });

  it('ignore la racine et les routes auth/onboarding/callback', () => {
    rememberPendingRoute('/');
    rememberPendingRoute('');
    rememberPendingRoute('/(auth)/login');
    rememberPendingRoute('/onboarding');
    rememberPendingRoute('/auth/callback?exchange_code=x');

    expect(consumePendingRoute()).toBeNull();
  });

  it('une route ignorée n’écrase pas une route valide déjà mémorisée', () => {
    rememberPendingRoute('/payment-success?tx_ref=KH-1');
    rememberPendingRoute('/(auth)/login');

    expect(consumePendingRoute()).toBe('/payment-success?tx_ref=KH-1');
  });
});
