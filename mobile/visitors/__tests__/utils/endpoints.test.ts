import { ENDPOINTS } from '@/api/endpoints';

/**
 * Smoke tests sur la table d'endpoints — protège contre les régressions
 * "un dev a changé le préfixe et tout casse silencieusement".
 */
describe('ENDPOINTS table', () => {
  it('auth routes sont sous /auth/* (sauf alias)', () => {
    expect(ENDPOINTS.auth.login).toBe('/auth/login');
    expect(ENDPOINTS.auth.me).toBe('/auth/me');
    expect(ENDPOINTS.auth.register).toBe('/auth/registerCustomer');
    expect(ENDPOINTS.auth.logout).toBe('/auth/logout');
    expect(ENDPOINTS.auth.verifyEmailOtp).toBe('/auth/verify-email-otp');
    expect(ENDPOINTS.auth.forgotPassword).toBe('/auth/forgot-password');
    expect(ENDPOINTS.auth.resetPassword).toBe('/auth/reset-password');
    expect(ENDPOINTS.auth.changePassword).toBe('/auth/update-password');
  });

  it('encode les paramètres URL dans les builders', () => {
    expect(ENDPOINTS.ads.detail('café & maison')).toContain(
      'caf%C3%A9%20%26%20maison',
    );
    expect(ENDPOINTS.users.publicProfile('user/with/slash')).toContain(
      'user%2Fwith%2Fslash',
    );
  });

  it('chat endpoints exposent toutes les actions Messenger', () => {
    expect(ENDPOINTS.conversations.messages('abc')).toMatch(/\/messages$/);
    expect(ENDPOINTS.conversations.attachments('abc')).toMatch(/\/attachments$/);
    expect(ENDPOINTS.conversations.typing('abc')).toMatch(/\/typing$/);
    expect(ENDPOINTS.conversations.read('abc')).toMatch(/\/read$/);
    expect(ENDPOINTS.messages.reactions('xyz')).toMatch(/\/reactions$/);
  });
});
