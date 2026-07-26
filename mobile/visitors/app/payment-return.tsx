import { Stack, useLocalSearchParams } from 'expo-router';

import PaymentSuccess from './payment-success';

/**
 * Alias for the legacy `/payment/return` route used by the web app's
 * payment gateway callback URLs. We forward straight to the shared
 * `payment-success` screen — it already polls `/payments/{txRef}/public-status`
 * and renders success / pending / failed UIs uniformly.
 *
 * Backend payment URLs are gateway-controlled; if they ever change the
 * return path back to /payment/return, this thin alias ensures the
 * mobile app still resolves it deterministically (vs a "route not
 * found" hard-fail inside Expo Router).
 */
export default function PaymentReturn() {
  // Consume the params so the inner Suspense / link doesn't re-render
  // with empty state if Expo Router introspects them.
  useLocalSearchParams<{ tx_ref?: string; txRef?: string; reference?: string; paymentId?: string }>();
  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <PaymentSuccess />
    </>
  );
}
