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
