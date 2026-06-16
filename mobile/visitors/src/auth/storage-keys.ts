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
