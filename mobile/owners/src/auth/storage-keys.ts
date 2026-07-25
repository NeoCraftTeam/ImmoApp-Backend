/**
 * Keys used with `expo-secure-store`. Centralised so an accidental
 * literal mismatch can't happen. SecureStore writes Keychain entries on
 * iOS and EncryptedSharedPreferences on Android — a stolen device can't
 * read them without unlocking.
 *
 * NOTE: the owner app uses a distinct token key from the visitor app so
 * a device with both installed keeps separate sessions.
 */
export const SESSION_KEY = 'keyhome.owners.session.token';
export const ONBOARDING_DONE_KEY = 'keyhome.owners.onboarding.done';

/**
 * Clé de session cloisonnée par environnement d'API : un token émis par
 * prod n'est pas valable sur preprod/local. SecureStore n'accepte que
 * [A-Za-z0-9._-] → on sanitize l'hôte.
 *
 * `client.ts` calcule la clé effective depuis la base URL résolue et
 * l'exporte (`SCOPED_SESSION_KEY`) — SessionProvider, intercepteurs et
 * téléchargements PDF doivent tous lire/écrire cette même entrée.
 */
export function scopedSessionKey(baseUrl: string): string {
  const suffix = String(baseUrl || 'default')
    .replace(/[^a-zA-Z0-9]/g, '')
    .slice(-24);
  return `${SESSION_KEY}.${suffix}`;
}
