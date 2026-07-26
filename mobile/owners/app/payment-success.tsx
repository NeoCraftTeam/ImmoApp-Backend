import { AlertTriangle, ArrowLeft, CheckCircle2, Clock, XCircle } from '@tamagui/lucide-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable } from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { usePublicPaymentStatus } from '@/hooks/usePayments';
import { useVerifyCreditPurchase } from '@/hooks/useCredits';
import { reportError } from '@/services/monitoring';
import { brand } from '@/theme/tokens';

const POLLING_TIMEOUT_MS = 60_000;

/**
 * Page de retour post-checkout owner. Identique au visiteur côté flux
 * (poll public-status + timeout 60s + retry), avec en plus un appel
 * **opportuniste** à `/credits/verify-purchase` pour pousser le balance
 * plus vite que le webhook backend. Si le user a payé un autre type
 * (subscription/boost), `verify-purchase` retourne `not_found` et on
 * laisse simplement le polling status faire son travail.
 */
export default function PaymentSuccessOwner() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { tx_ref, txRef, reference, status } = useLocalSearchParams<{
    tx_ref?: string;
    txRef?: string;
    reference?: string;
    status?: string;
  }>();
  const ref = tx_ref ?? txRef;
  const gatewayReference =
    typeof reference === 'string' && reference !== '' ? reference : undefined;
  const qc = useQueryClient();
  const { data, isLoading, refetch } = usePublicPaymentStatus(ref ?? gatewayReference);
  const verifyCredit = useVerifyCreditPurchase();

  const [timedOut, setTimedOut] = useState(false);

  // Edge case : si l'utilisateur arrive ici sans tx_ref (deep-link
  // casse, gateway timeout, navigation manuelle) on affiche un
  // ecran d'erreur clair au lieu de tourner indefiniment sur le
  // spinner sans pouvoir rien faire.
  if (!ref && !gatewayReference) {
    return (
      <>
        <Stack.Screen options={{ headerShown: false }} />
        <YStack
          flex={1}
          backgroundColor="$background"
          paddingTop={insets.top + 12}
          paddingHorizontal={24}
          paddingBottom={insets.bottom + 16}
          alignItems="center"
          justifyContent="center"
          gap={16}
        >
          <AlertTriangle size={48} color={brand.warning} />
          <H2 fontSize={22} fontWeight="800" textAlign="center" color={brand.warning}>
            Référence de transaction manquante
          </H2>
          <Paragraph fontSize={14} color="$slate700" textAlign="center" lineHeight={20}>
            Nous n'avons pas reçu l'identifiant de paiement (tx_ref). Si vous venez
            de payer, retournez à l'écran précédent puis touchez « Réessayer ».
            Sinon ouvrez votre historique de paiements pour vérifier le statut.
          </Paragraph>
          <XStack gap={10} marginTop={6}>
            <Button
              backgroundColor="$brand"
              color="white"
              fontWeight="800"
              borderRadius={12}
              onPress={() => router.replace('/payments' as never)}
            >
              Voir l'historique
            </Button>
            <Button
              backgroundColor="$slate100"
              color="$slate900"
              fontWeight="700"
              borderRadius={12}
              onPress={() => router.replace('/(tabs)/dashboard')}
            >
              Tableau de bord
            </Button>
          </XStack>
        </YStack>
      </>
    );
  }

  // Verify-purchase opportuniste — idempotent cote backend. On
  // ne swallow PAS l'erreur silencieusement : si verify echoue
  // (4xx, payload corrompu), on log dans monitoring pour ne pas
  // perdre la trace. Le polling status reste le fallback principal,
  // donc l'echec ici n'a pas de consequence UX.
  useEffect(() => {
    if (!ref && !gatewayReference) return;
    verifyCredit.mutate(
      {
        ...(ref ? { tx_ref: ref } : {}),
        ...(gatewayReference ? { reference: gatewayReference } : {}),
        ...(typeof status === 'string' && status !== ''
          ? { gateway_redirect_status: status }
          : {}),
      },
      {
        onError: (err) => {
          reportError(err, {
            tx_ref: ref,
            reference: gatewayReference,
            stage: 'verify-purchase-opportunistic',
          });
        },
      },
    );
  }, [ref, gatewayReference, status]);

  // Cache invalidation defensive — meme si PaymentSheet l'a deja
  // fait, on re-invalide au cas ou l'utilisateur arrive ici via un
  // deep-link direct (sans passer par PaymentSheet) OU si le payment
  // se settle plus tard via webhook.
  useEffect(() => {
    if (data?.status === 'success' || data?.status === 'succeeded') {
      qc.invalidateQueries({ queryKey: ['credits-balance'] });
      qc.invalidateQueries({ queryKey: ['subscription-current'] });
      qc.invalidateQueries({ queryKey: ['payments-history'] });
    }
  }, [data?.status, qc]);

  // Armé dès qu'on a une référence quelconque (tx_ref OU reference
  // passerelle, ex. retour Stripe) — sinon l'écran resterait figé sur
  // « en cours » sans jamais proposer Réessayer.
  useEffect(() => {
    if (!ref && !gatewayReference) return;
    if (data?.status && data.status !== 'pending') return;
    const t = setTimeout(() => setTimedOut(true), POLLING_TIMEOUT_MS);
    return () => clearTimeout(t);
  }, [ref, gatewayReference, data?.status]);

  let icon = <Clock size={56} color={brand.warning} />;
  let title = 'Paiement en cours…';
  let body = 'Nous confirmons votre transaction. Cela ne prend que quelques secondes.';
  let tint: string = brand.warning;

  if (timedOut && (!data?.status || data.status === 'pending')) {
    icon = <Clock size={56} color={brand.warning} />;
    title = 'Vérification plus longue que prévu';
    body =
      'Votre paiement est probablement en cours de traitement côté banque. ' +
      'Vous recevrez une notification dès qu’il sera confirmé. ' +
      'Vous pouvez fermer cette page sans risque.';
    tint = brand.warning;
  }

  // Statut terminal hors success/failed/cancelled : le poll est arrêté par
  // le hook (refunded, statut backend inattendu) — ne jamais rester figé
  // sur « Paiement en cours… » sans issue.
  const isOtherTerminal = Boolean(
    data?.status
      && !['pending', 'success', 'succeeded', 'failed', 'cancelled'].includes(data.status),
  );

  if (data?.status === 'success' || data?.status === 'succeeded') {
    icon = <CheckCircle2 size={56} color={brand.success} />;
    title = 'Paiement confirmé';
    body = data.message ?? 'Votre transaction a été créditée avec succès.';
    tint = brand.success;
  } else if (data?.status === 'failed' || data?.status === 'cancelled') {
    icon = <XCircle size={56} color={brand.danger} />;
    title = data.status === 'cancelled' ? 'Paiement annulé' : 'Paiement échoué';
    body =
      data.message ??
      'La transaction n’a pas pu aboutir. Réessayez ou contactez le support.';
    tint = brand.danger;
  } else if (isOtherTerminal) {
    icon = <XCircle size={56} color={brand.slate500} />;
    title = data?.status === 'refunded' ? 'Paiement remboursé' : 'Transaction clôturée';
    body =
      data?.message ??
      'Cette transaction est terminée. Consultez votre historique de paiements pour le détail.';
    tint = brand.slate500;
  }

  const goHome = () => router.replace('/(tabs)/dashboard');

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
          onPress={goHome}
          hitSlop={8}
          accessibilityRole="button"
          accessibilityLabel="Retour"
        >
          <YStack
            width={36}
            height={36}
            borderRadius={18}
            backgroundColor="$slate100"
            alignItems="center"
            justifyContent="center"
          >
            <ArrowLeft size={18} color={brand.slate700} />
          </YStack>
        </Pressable>

        <YStack flex={1} alignItems="center" justifyContent="center" gap={16}>
          {isLoading && !data ? <ActivityIndicator color={brand.primary} /> : icon}
          <H2 fontSize={24} fontWeight="800" textAlign="center" color={tint}>
            {title}
          </H2>
          <Paragraph fontSize={14.5} color="$slate700" textAlign="center" lineHeight={22}>
            {body}
          </Paragraph>
          {data?.amount != null ? (
            <Paragraph fontSize={16} fontWeight="900" color="$slate900">
              {data.amount.toLocaleString('fr-FR')} {data.currency ?? 'FCFA'}
            </Paragraph>
          ) : null}
          {(data?.status === 'success' || data?.status === 'succeeded') ? (
            <Button
              backgroundColor="$brand"
              color="white"
              fontWeight="800"
              borderRadius={12}
              onPress={goHome}
              marginTop={6}
            >
              Retour au tableau de bord
            </Button>
          ) : null}
          {(data?.status === 'failed' || data?.status === 'cancelled' || isOtherTerminal) ? (
            <XStack gap={10} marginTop={6}>
              <Button
                backgroundColor="$slate100"
                color="$slate900"
                fontWeight="700"
                borderRadius={12}
                onPress={goHome}
              >
                Retour
              </Button>
            </XStack>
          ) : null}
          {timedOut && (!data?.status || data.status === 'pending') ? (
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
                onPress={goHome}
              >
                Retour
              </Button>
            </XStack>
          ) : null}
        </YStack>
      </YStack>
    </>
  );
}
