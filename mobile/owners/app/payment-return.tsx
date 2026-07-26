import { Redirect, useLocalSearchParams } from 'expo-router';

/**
 * Alias legacy : certaines configurations gateway peuvent rediriger
 * vers `/payment/return` au lieu de `/payment-success`. On forwarde
 * tous les query params pour ne perdre ni `tx_ref` ni `reference`.
 */
export default function PaymentReturnAlias() {
  const params = useLocalSearchParams();
  return (
    <Redirect
      href={{
        pathname: '/payment-success',
        params: params as Record<string, string>,
      }}
    />
  );
}
