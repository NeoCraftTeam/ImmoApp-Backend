import { ArrowLeft, CheckCircle2, Clock, XCircle } from '@tamagui/lucide-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable } from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { usePublicPaymentStatus } from '@/hooks/usePaymentStatus';
import { brand } from '@/theme/tokens';

const POLLING_TIMEOUT_MS = 60_000;

/**
 * Page atterrissage post-paiement. Lit `tx_ref` depuis l'URL et
 * interroge `/payments/{txRef}/public-status` en poll 3 s tant que
 * le statut est `pending`. Au bout de 60 s on bascule sur un état
 * "vérification longue" avec retry manuel — évite le spinner infini
 * si le webhook backend tarde ou échoue.
 */
export default function PaymentSuccess() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { tx_ref, txRef } = useLocalSearchParams<{ tx_ref?: string; txRef?: string }>();
  const ref = tx_ref ?? txRef;
  const { data, isLoading, refetch } = usePublicPaymentStatus(ref);

  // Timeout 60 s : on stoppe le poll perçu et on bascule en "long"
  // pour laisser le user choisir entre réessayer ou écrire au support.
  const [timedOut, setTimedOut] = useState(false);
  useEffect(() => {
    if (!ref) return;
    if (data?.status && data.status !== 'pending') return;
    const t = setTimeout(() => setTimedOut(true), POLLING_TIMEOUT_MS);
    return () => clearTimeout(t);
  }, [ref, data?.status]);

  let icon = <Clock size={56} color={brand.warning} />;
  let title = 'Paiement en cours…';
  let body = 'Nous confirmons votre transaction. Cela ne prend que quelques secondes.';
  let tint: string = brand.warning;

  if (timedOut && (!data?.status || data.status === 'pending')) {
    icon = <Clock size={56} color={brand.warning} />;
    title = 'Vérification plus longue que prévu';
    body =
      'Votre paiement est probablement en cours de traitement côté banque. ' +
      'Vous recevrez une notification dès qu\'il sera confirmé. ' +
      'Vous pouvez fermer cette page sans risque.';
    tint = brand.warning;
  }

  if (data?.status === 'success') {
    icon = <CheckCircle2 size={56} color={brand.success} />;
    title = 'Paiement confirmé';
    body = data.message ?? 'Votre transaction a été créditée avec succès.';
    tint = brand.success;
  } else if (data?.status === 'failed') {
    icon = <XCircle size={56} color={brand.danger} />;
    title = 'Paiement échoué';
    body = data.message ?? 'La transaction n\'a pas pu aboutir. Réessayez ou contactez le support.';
    tint = brand.danger;
  }

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack
        flex={1}
        backgroundColor="$background"
        paddingTop={insets.top + 12}
        paddingHorizontal={24}
        paddingBottom={insets.bottom + 16}
        gap={20}
      >
        <Pressable
          onPress={() => router.replace('/(tabs)/home')}
          hitSlop={8}
          accessibilityRole="button"
          accessibilityLabel="Retour à l'accueil"
        >
          <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
            <ArrowLeft size={18} color={brand.slate700} />
          </YStack>
        </Pressable>

        <YStack flex={1} alignItems="center" justifyContent="center" gap={16}>
          {isLoading && !data ? <ActivityIndicator /> : icon}
          <H2 fontSize={24} fontWeight="700" textAlign="center" color={tint}>{title}</H2>
          <Paragraph fontSize={14.5} color="$slate700" textAlign="center" lineHeight={22}>
            {body}
          </Paragraph>
          {data?.amount != null && (
            <Paragraph fontSize={16} fontWeight="800" color="$slate900">
              {data.amount.toLocaleString('fr-FR')} {data.currency ?? 'XAF'}
            </Paragraph>
          )}
          {data?.status === 'success' && data.ad_slug && (
            <Button
              backgroundColor="$brand"
              color="white"
              fontWeight="700"
              borderRadius={12}
              onPress={() => router.replace({ pathname: '/ads/[slug]', params: { slug: data.ad_slug! } })}
            >
              Voir l'annonce
            </Button>
          )}
          {data?.status === 'success' && !data.ad_slug && (
            <Button backgroundColor="$brand" color="white" fontWeight="700" borderRadius={12} onPress={() => router.replace('/(tabs)/home')}>
              Retour à l'accueil
            </Button>
          )}
          {data?.status === 'failed' && (
            <Button backgroundColor="$slate100" color="$slate900" fontWeight="700" borderRadius={12} onPress={() => router.replace('/(tabs)/home')}>
              Retour à l'accueil
            </Button>
          )}
          {timedOut && (!data?.status || data.status === 'pending') && (
            <XStack gap={10} marginTop={6}>
              <Button
                backgroundColor="$slate900"
                color="white"
                fontWeight="700"
                borderRadius={12}
                onPress={() => {
                  setTimedOut(false);
                  refetch();
                }}
              >
                Réessayer
              </Button>
              <Button
                backgroundColor="$slate100"
                color="$slate900"
                fontWeight="700"
                borderRadius={12}
                onPress={() => router.replace('/(tabs)/home')}
              >
                Retour
              </Button>
            </XStack>
          )}
        </YStack>
      </YStack>
    </>
  );
}
