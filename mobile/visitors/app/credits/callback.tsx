import { CheckCircle2, Clock, XCircle } from '@tamagui/lucide-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Pressable } from 'react-native';
import { H2, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useQueryClient } from '@tanstack/react-query';

import { queryKeys } from '@/lib/query-keys';
import {
  clearPendingCreditPurchase,
  loadPendingCreditPurchase,
  pollVerifyPurchase,
  type PaymentOutcome,
} from '@/services/credit-purchase';
import { brand } from '@/theme/tokens';

/**
 * Atterrissage du deep-link de paiement (`keyhome://credits/callback`).
 *
 * Le flux chaud (app vivante) est géré dans le modal d'achat via
 * `openAuthSessionAsync` ; cet écran couvre le COLD START : iOS a tué
 * l'app pendant la validation mobile money et l'OS la relance
 * directement sur ce deep-link. On lit `tx_ref` (ou on le récupère
 * depuis l'achat persisté), on réconcilie avec le backend, puis on
 * affiche le résultat avec un CTA vers l'écran crédits.
 */
export default function CreditsCallbackScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();
  const params = useLocalSearchParams<{
    tx_ref?: string;
    txRef?: string;
    reference?: string;
    status?: string;
  }>();

  const [outcome, setOutcome] = useState<PaymentOutcome | 'verifying' | 'no_ref'>('verifying');
  const startedRef = useRef(false);

  useEffect(() => {
    if (startedRef.current) {
      return;
    }
    startedRef.current = true;

    let cancelled = false;

    const run = async () => {
      const paramRef =
        (typeof params.tx_ref === 'string' && params.tx_ref) ||
        (typeof params.txRef === 'string' && params.txRef) ||
        (typeof params.reference === 'string' && params.reference) ||
        '';

      const pending = paramRef === '' ? await loadPendingCreditPurchase() : null;
      const lookupRef = paramRef !== '' ? paramRef : (pending?.txRef ?? '');

      if (lookupRef === '') {
        if (!cancelled) {
          setOutcome('no_ref');
        }
        return;
      }

      const result = await pollVerifyPurchase(lookupRef, {
        attempts: 8,
        intervalMs: 1500,
        gatewayRedirectStatus: typeof params.status === 'string' ? params.status : null,
      });

      if (result !== 'pending') {
        await clearPendingCreditPurchase();
      }
      queryClient.invalidateQueries({ queryKey: queryKeys.creditsBalance() });
      queryClient.invalidateQueries({ queryKey: queryKeys.paymentsHistory() });

      if (!cancelled) {
        setOutcome(result);
      }
    };

    void run();

    return () => {
      cancelled = true;
    };
  }, [params.tx_ref, params.txRef, params.reference, params.status, queryClient]);

  const goToCredits = () => router.replace('/credits');

  const view = {
    verifying: {
      icon: <Spinner size="large" color={brand.primary} />,
      title: 'Confirmation du paiement…',
      body: 'Un instant, nous vérifions la transaction auprès de la passerelle.',
      cta: null as string | null,
    },
    completed: {
      icon: <CheckCircle2 size={56} color={brand.success} />,
      title: 'Paiement réussi',
      body: 'Vos crédits ont été ajoutés à votre solde.',
      cta: 'Voir mes crédits',
    },
    pending: {
      icon: <Clock size={56} color={brand.warning} />,
      title: 'Paiement en cours',
      body:
        'Nous confirmons votre paiement auprès de la passerelle. Vos crédits s’ajouteront automatiquement dès validation — vous pouvez suivre le statut dans l’historique.',
      cta: 'Voir l’historique',
    },
    failed: {
      icon: <XCircle size={56} color={brand.danger} />,
      title: 'Paiement échoué',
      body: 'La transaction a été refusée ou annulée. Si un montant a malgré tout été débité, il sera automatiquement pris en compte.',
      cta: 'Voir mes crédits',
    },
    no_ref: {
      icon: <Clock size={56} color={brand.warning} />,
      title: 'Référence introuvable',
      body:
        'Nous n’avons pas reçu la référence de ce paiement. Si vous avez validé un paiement, il sera confirmé automatiquement — consultez votre historique.',
      cta: 'Voir mes crédits',
    },
  }[outcome];

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack
        flex={1}
        backgroundColor="$background"
        paddingTop={insets.top + 12}
        paddingHorizontal={24}
        paddingBottom={insets.bottom + 16}
      >
        <YStack flex={1} alignItems="center" justifyContent="center" gap={16}>
          {view.icon}
          <H2 fontSize={22} fontWeight="800" textAlign="center" color="$slate900">
            {view.title}
          </H2>
          <Paragraph fontSize={14.5} color="$slate700" textAlign="center" lineHeight={22}>
            {view.body}
          </Paragraph>
          {view.cta ? (
            <Pressable onPress={goToCredits} accessibilityRole="button">
              <XStack
                backgroundColor="$brand"
                paddingHorizontal={24}
                paddingVertical={13}
                borderRadius={14}
                alignItems="center"
                justifyContent="center"
                marginTop={8}
              >
                <Paragraph color="$brandText" fontWeight="800" fontSize={15}>
                  {view.cta}
                </Paragraph>
              </XStack>
            </Pressable>
          ) : null}
        </YStack>
      </YStack>
    </>
  );
}
