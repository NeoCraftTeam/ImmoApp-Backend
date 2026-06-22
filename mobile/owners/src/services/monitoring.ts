import Constants from 'expo-constants';

/**
 * Monitoring lazy-init pour l'app owner — Sentry capture des erreurs
 * + breadcrumbs analytics. No-op en Expo Go ou si `EXPO_PUBLIC_SENTRY_DSN`
 * est vide, pour ne pas polluer les logs dev. Cohérent avec le client.
 */

type SentryShape = {
  init: (opts: Record<string, unknown>) => void;
  captureException: (err: unknown, hint?: { extra?: Record<string, unknown> }) => void;
  addBreadcrumb: (b: { category?: string; message?: string; data?: Record<string, unknown>; level?: string }) => void;
  setUser: (user: Record<string, unknown> | null) => void;
};

let sentry: SentryShape | null = null;
let initialised = false;

function isExpoGo(): boolean {
  return Constants.executionEnvironment === 'storeClient';
}

export function initMonitoring(): void {
  if (initialised) return;
  initialised = true;

  const dsn = process.env.EXPO_PUBLIC_SENTRY_DSN;
  if (!dsn || isExpoGo()) {
    return; // no-op
  }

  try {
    const mod = require('@sentry/react-native') as SentryShape;
    mod.init({
      dsn,
      tracesSampleRate: 0.2,
      enableNative: true,
      environment: __DEV__ ? 'development' : 'production',
    });
    sentry = mod;
  } catch {
    /* sentry non installé en dev — silent. */
  }
}

export function reportError(err: unknown, extra?: Record<string, unknown>): void {
  if (sentry) {
    sentry.captureException(err, { extra });
  } else if (__DEV__) {
    // eslint-disable-next-line no-console
    console.warn('[monitoring]', err, extra);
  }
}

export function trackEvent(name: string, data?: Record<string, unknown>): void {
  if (sentry) {
    sentry.addBreadcrumb({ category: 'event', message: name, data, level: 'info' });
  }
}

export function setUserContext(user: { id: string; email?: string } | null): void {
  if (!sentry) return;
  if (user) {
    sentry.setUser({ id: user.id, email: user.email });
  } else {
    sentry.setUser(null);
  }
}

export function clearUserContext(): void {
  setUserContext(null);
}
