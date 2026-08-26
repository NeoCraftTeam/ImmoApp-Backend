/**
 * Tests du module de préservation de route post-login : une destination
 * gardée par un SignInWall doit être rejouée après connexion, et jamais
 * une route du groupe (auth) ni la racine.
 */
import { consumePendingRoute, rememberPendingRoute } from '@/auth/pending-route';

beforeEach(() => {
  // Draine l'état module entre les tests.
  consumePendingRoute();
});

describe('pending-route', () => {
  it('mémorise puis consomme une route avec query', () => {
    rememberPendingRoute('/reservations?tab=upcoming');

    expect(consumePendingRoute()).toBe('/reservations?tab=upcoming');
    expect(consumePendingRoute()).toBeNull();
  });

  it('la dernière route mémorisée gagne', () => {
    rememberPendingRoute('/refunds');
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
    rememberPendingRoute('/reservations');
    rememberPendingRoute('/(auth)/login');

    expect(consumePendingRoute()).toBe('/reservations');
  });
});
