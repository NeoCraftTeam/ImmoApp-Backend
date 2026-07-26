/**
 * Keys used with `expo-secure-store`. Centralised so an accidental
 * literal "session-token" vs "session_token" mismatch can't happen.
 *
 * SecureStore writes Keychain entries on iOS and EncryptedSharedPreferences
 * on Android — a stolen device can't read them without unlocking the
 * device.
 */
export const SESSION_KEY = 'keyhome.session.token';
export const ONBOARDING_DONE_KEY = 'keyhome.onboarding.done';
export const PERMISSIONS_PRIMED_KEY = 'keyhome.permissions.primed';

/**
 * Clé de session cloisonnée par environnement d'API : un token émis par
 * prod n'est pas valable sur preprod/local (bases + tokens distincts).
 * SecureStore n'accepte que [A-Za-z0-9._-] → on sanitize l'hôte.
 *
 * Utilisée par `client.ts` (source de vérité, calculée depuis la base URL
 * résolue) puis consommée par le SessionProvider — les deux couches DOIVENT
 * lire/écrire la même clé, sinon le fallback cold-start et le cleanup 401
 * opèrent sur une entrée fantôme.
 */
export function scopedSessionKey(baseUrl: string): string {
  const suffix = String(baseUrl || 'default')
    .replace(/[^a-zA-Z0-9]/g, '')
    .slice(-24);
  return `${SESSION_KEY}.${suffix}`;
}
