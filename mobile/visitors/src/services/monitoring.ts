import * as Sentry from '@sentry/react-native';
import Constants, { ExecutionEnvironment } from 'expo-constants';
import type { ErrorInfo } from 'react';

/**
 * Sentry bootstrap. Init once at app launch via `initMonitoring()`.
 *
 * - **DSN** lu depuis `EXPO_PUBLIC_SENTRY_DSN` (env vars) → si absent,
 *   on no-op (utile en local pour ne pas spammer le projet Sentry).
 * - **Expo Go** : on désactive Sentry car les sources maps natives ne
 *   sont pas dispo et les warnings polluent la console — il faut un
 *   dev-build / production build pour bénéficier des stack traces.
 * - **`tracesSampleRate: 0.1`** : 10 % des transactions web vitals
 *   échantillonnées. Bump à 1.0 sur staging.
 *
 * Public API :
 *   - `initMonitoring()` : appeler une fois au boot
 *   - `reportError(error, info?)` : capturer manuellement
 *   - `setUserContext({id, email})` : associer la session
 *   - `clearUserContext()` : à appeler sur signOut
 */
const IS_EXPO_GO =
  Constants.executionEnvironment === ExecutionEnvironment.StoreClient;

const DSN = process.env.EXPO_PUBLIC_SENTRY_DSN;

let initialized = false;

export function initMonitoring(): void {
  if (initialized || IS_EXPO_GO || !DSN) return;
  initialized = true;
  try {
    Sentry.init({
      dsn: DSN,
      tracesSampleRate: __DEV__ ? 1.0 : 0.1,
      enableAutoSessionTracking: true,
      attachStacktrace: true,
      environment: __DEV__ ? 'development' : 'production',
      // Filtre les noises (network blips qu'on remonte déjà côté UI).
      beforeSend(event) {
        const msg = event.message ?? event.exception?.values?.[0]?.value ?? '';
        if (typeof msg === 'string' && /Network Error|aborted/i.test(msg)) {
          return null;
        }
        return event;
      },
    });
  } catch {
    /* Sentry init failures ne doivent JAMAIS bloquer le boot */
  }
}

export function reportError(error: Error, info?: ErrorInfo): void {
  if (!initialized) return;
  try {
    Sentry.withScope((scope) => {
      if (info?.componentStack) {
        scope.setContext('react', { componentStack: info.componentStack });
      }
      Sentry.captureException(error);
    });
  } catch {
    /* ignore */
  }
}

export function setUserContext(user: { id: string; email?: string }): void {
  if (!initialized) return;
  try {
    Sentry.setUser({ id: user.id, email: user.email });
  } catch {
    /* ignore */
  }
}

export function clearUserContext(): void {
  if (!initialized) return;
  try {
    Sentry.setUser(null);
  } catch {
    /* ignore */
  }
}

/** Track a non-error event (mouse-click, conversion, etc.). */
export function trackEvent(name: string, data?: Record<string, unknown>): void {
  if (!initialized) return;
  try {
    Sentry.addBreadcrumb({
      category: 'analytics',
      message: name,
      level: 'info',
      data,
    });
  } catch {
    /* ignore */
  }
}
