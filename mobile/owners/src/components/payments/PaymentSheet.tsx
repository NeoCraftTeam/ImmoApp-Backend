import { Lock, ShieldCheck, X } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { Alert, Pressable } from 'react-native';
import { Button, Input, Paragraph, Sheet, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/extract-error';
import { PaymentMethodPicker } from '@/components/payments/PaymentMethodPicker';
import {
  useInitiatePayment,
  usePaymentMethods,
  useStripeMethods,
} from '@/hooks/usePayments';
import { buildCallbackUrl, openHostedCheckout } from '@/services/checkout';
import { trackEvent } from '@/services/monitoring';
import { brand } from '@/theme/tokens';
import { formatFcfa } from '@/utils/format';
import type { InitiatePaymentInput, PaymentMethod, PaymentPurpose } from '@/types/payment';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Description courte du paiement, ex: "Pack 500 crédits" */
  title: string;
  subtitle?: string;
  amount: number;
  purpose: PaymentPurpose;
  /** Payload spécifique selon `purpose` (plan_id, billing_period, package id…). */
  extraPayload?: Partial<InitiatePaymentInput>;
  /** Callback succès du flow checkout. Reçoit le tx_ref renvoyé par le gateway. */
  onSuccess?: (txRef: string) => void;
}

/**
 * Bottom-sheet de paiement unifié — utilisé par les écrans :
 *   - subscriptions (subscribe / upgrade)
 *   - credits (achat package)
 *   - pro-services (purchase)
 *   - boost (apply boost)
 *
 * Flow :
 *   1. Charge les méthodes dispos (`/payments/methods`)
 *   2. User pick méthode + saisit phone (mobile money)
 *   3. Mutation `initiate_payment` → reçoit `payment_link` + `tx_ref`
 *   4. Ouvre `WebBrowser.openAuthSessionAsync` avec deep-link return
 *   5. Au retour, push `/payment-success?tx_ref=...` qui polle le statut
 *
 * Gestion d'erreurs : tout est try-catched, les erreurs Axios sont
 * normalisées par `extractApiErrorMessage`. Pas de navigation
 * implicite — le caller décide via `onSuccess` ou via la page success.
 */
export function PaymentSheet({
  open,
  onOpenChange,
  title,
  subtitle,
  amount,
  purpose,
  extraPayload,
  onSuccess,
}: Props) {
  const router = useRouter();
  const qc = useQueryClient();
  const { data: methods = [], isLoading: methodsLoading } = usePaymentMethods(open);
  const initiate = useInitiatePayment();

  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
  const [phone, setPhone] = useState<string>('');
  const [submitting, setSubmitting] = useState(false);
  const [selectedSavedCardId, setSelectedSavedCardId] = useState<string | null>(null);

  const isCardMethod = selectedMethod?.channel === 'card'
    || selectedMethod?.gateway === 'stripe';
  const { data: savedCards = [], isLoading: savedCardsLoading } = useStripeMethods(
    open && isCardMethod,
  );

  // Auto-pick la première méthode si une seule est dispo
  useEffect(() => {
    if (open && !selectedMethod && methods.length > 0) {
      setSelectedMethod(methods.find((m) => m.is_default) ?? methods[0] ?? null);
    }
  }, [open, methods, selectedMethod]);

  useEffect(() => {
    if (!open || !isCardMethod || savedCards.length === 0) {
      setSelectedSavedCardId(null);
      return;
    }

    const defaultCard = savedCards.find((c) => c.is_default) ?? savedCards[0];
    setSelectedSavedCardId(defaultCard?.id ?? null);
  }, [open, isCardMethod, savedCards]);

  // Reset à la fermeture
  useEffect(() => {
    if (!open) {
      setSelectedMethod(null);
      setPhone('');
      setSubmitting(false);
      setSelectedSavedCardId(null);
    }
  }, [open]);

  const requiresPhone = useMemo(
    () => Boolean(selectedMethod?.requires_phone || selectedMethod?.channel === 'mobile_money'),
    [selectedMethod],
  );

  const canSubmit = Boolean(selectedMethod) && (!requiresPhone || phone.trim().length >= 8);

  const finishWithTxRef = (
    txRef: string,
    extras?: { status?: string; reference?: string },
  ) => {
    onOpenChange(false);
    qc.invalidateQueries({ queryKey: ['credits-balance'] });
    qc.invalidateQueries({ queryKey: ['subscription-current'] });
    qc.invalidateQueries({ queryKey: ['payments-history'] });
    qc.invalidateQueries({ queryKey: ['stripe-methods'] });
    onSuccess?.(txRef);
    router.push({
      pathname: '/payment-success',
      params: {
        tx_ref: txRef,
        ...(extras?.reference ? { reference: extras.reference } : {}),
        ...(extras?.status ? { status: extras.status } : {}),
      },
    } as never);
  };

  const handleSubmit = async () => {
    // Guard double-submit : si l'utilisateur tap 2 fois en moins
    // d'un tick React (avant que setSubmitting(true) ne re-render le
    // bouton en disabled), on aurait 2 mutateAsync en parallele →
    // 2 tx_ref orphelins, double charge potentielle.
    if (!selectedMethod || submitting || initiate.isPending) return;
    setSubmitting(true);
    trackEvent('payment.sheet.submit', { purpose, gateway: selectedMethod.gateway });

    try {
      const payload: InitiatePaymentInput = {
        amount,
        type: purpose,
        payment_method: selectedMethod.code,
        phone_number: requiresPhone ? phone.trim() : undefined,
        // Deep-link natif → le backend l'enveloppe dans le pont HTTPS pour que
        // l'onglet in-app se ferme tout seul en fin de paiement.
        callback_url: buildCallbackUrl(),
        ...(isCardMethod && selectedSavedCardId
          ? { payment_method_id: selectedSavedCardId }
          : {}),
        ...extraPayload,
      };
      const init = await initiate.mutateAsync(payload);
      const link = init.payment_link ?? init.payment_url;
      const txRef = init.tx_ref;
      const gatewayStatus = init.status;

      if (
        txRef
        && (gatewayStatus === 'success' || gatewayStatus === 'succeeded')
      ) {
        finishWithTxRef(txRef, { status: 'success' });
        return;
      }

      if (txRef && gatewayStatus === 'failed') {
        Alert.alert(
          'Paiement refusé',
          'Votre banque a refusé cette transaction. Essayez une autre carte ou un autre moyen.',
        );
        setSubmitting(false);
        return;
      }

      if (gatewayStatus === 'requires_action') {
        Alert.alert(
          'Validation requise',
          'Ce paiement nécessite une confirmation 3D Secure. Utilisez keyhome.app ou choisissez Mobile Money.',
        );
        setSubmitting(false);
        return;
      }

      if (!link || !txRef || !link.startsWith('http')) {
        Alert.alert(
          'Erreur',
          'Le gateway de paiement n’a pas renvoyé d’URL ou de tx_ref.',
        );
        setSubmitting(false);
        return;
      }

      onOpenChange(false); // fermer le sheet avant d'ouvrir le browser
      const result = await openHostedCheckout(link, txRef);

      if (result.cancelled) {
        Alert.alert('Paiement annule', 'Vous avez ferme la fenetre de paiement.');
        return;
      }
      if (result.error) {
        Alert.alert('Erreur', result.error.message);
        return;
      }
      const finalRef = result.txRef ?? txRef;
      const gatewayReference =
        result.reference ?? result.paymentId ?? undefined;

      finishWithTxRef(finalRef, {
        status: result.status ?? undefined,
        reference: gatewayReference,
      });
    } catch (err) {
      Alert.alert('Erreur', extractApiErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Sheet
      modal
      open={open}
      onOpenChange={onOpenChange}
      snapPoints={[80]}
      dismissOnSnapToBottom
      animation="medium"
    >
      <Sheet.Overlay />
      <Sheet.Frame padding={0} gap={0} backgroundColor="$background">
        <XStack
          padding={16}
          alignItems="center"
          borderBottomWidth={0.5}
          borderBottomColor="$slate300"
        >
          <YStack flex={1} gap={2}>
            <Paragraph fontSize={17} fontWeight="900" color="$slate900">
              {title}
            </Paragraph>
            {subtitle ? (
              <Paragraph fontSize={12.5} color="$slate500">
                {subtitle}
              </Paragraph>
            ) : null}
          </YStack>
          <Pressable onPress={() => onOpenChange(false)} hitSlop={10} accessibilityLabel="Fermer">
            <X size={20} color={brand.slate700} />
          </Pressable>
        </XStack>

        <Sheet.ScrollView contentContainerStyle={{ padding: 16, gap: 16 }}>
          {/* Récap */}
          <YStack
            padding={14}
            gap={4}
            borderRadius={14}
            backgroundColor={brand.primaryAlpha10}
          >
            <Paragraph fontSize={11.5} color={brand.primaryHover} fontWeight="700">
              MONTANT À PAYER
            </Paragraph>
            <Paragraph fontSize={28} fontWeight="900" color="$slate900">
              {formatFcfa(amount)}
            </Paragraph>
          </YStack>

          {/* Méthodes */}
          <YStack gap={10}>
            <Paragraph fontSize={13} fontWeight="800" color="$slate900">
              Méthode de paiement
            </Paragraph>
            {methodsLoading ? (
              <YStack height={120} alignItems="center" justifyContent="center">
                <Spinner color={brand.primary} />
              </YStack>
            ) : (
              <PaymentMethodPicker
                methods={methods}
                selected={selectedMethod?.code}
                onSelect={setSelectedMethod}
              />
            )}
          </YStack>

          {/* Cartes enregistrées (Stripe) */}
          {isCardMethod ? (
            <YStack gap={8}>
              <Paragraph fontSize={13} fontWeight="800" color="$slate900">
                Carte enregistrée
              </Paragraph>
              {savedCardsLoading ? (
                <YStack height={72} alignItems="center" justifyContent="center">
                  <Spinner color={brand.primary} />
                </YStack>
              ) : savedCards.length === 0 ? (
                <Paragraph fontSize={12} color="$slate500">
                  Aucune carte enregistrée — vous serez redirigé vers le checkout sécurisé.
                </Paragraph>
              ) : (
                <YStack gap={8}>
                  {savedCards.map((card) => {
                    const selected = selectedSavedCardId === card.id;
                    return (
                      <Pressable
                        key={card.id}
                        onPress={() => setSelectedSavedCardId(card.id)}
                        accessibilityRole="radio"
                        accessibilityState={{ selected }}
                      >
                        <XStack
                          padding={12}
                          gap={10}
                          borderRadius={12}
                          borderWidth={selected ? 2 : 1}
                          borderColor={selected ? brand.primary : '$slate300'}
                          backgroundColor={selected ? brand.primaryAlpha10 : '$background'}
                          alignItems="center"
                        >
                          <Paragraph flex={1} fontSize={13} fontWeight="700" color="$slate900">
                            {card.brand} ·••• {card.last4}
                            {card.is_default ? ' · Par défaut' : ''}
                          </Paragraph>
                        </XStack>
                      </Pressable>
                    );
                  })}
                  <Pressable
                    onPress={() => setSelectedSavedCardId(null)}
                    accessibilityRole="radio"
                    accessibilityState={{ selected: selectedSavedCardId === null }}
                  >
                    <Paragraph fontSize={12.5} fontWeight="700" color={brand.primary}>
                      + Utiliser une autre carte
                    </Paragraph>
                  </Pressable>
                </YStack>
              )}
            </YStack>
          ) : null}

          {/* Phone (mobile money) */}
          {requiresPhone ? (
            <YStack gap={6}>
              <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
                Numéro mobile money
              </Paragraph>
              <Input
                value={phone}
                onChangeText={setPhone}
                placeholder="ex. 6 90 12 34 56"
                keyboardType="phone-pad"
                autoComplete="tel"
              />
              <Paragraph fontSize={11} color="$slate500">
                Une demande de paiement sera envoyée sur ce numéro.
              </Paragraph>
            </YStack>
          ) : null}

          {/* Trust strip */}
          <XStack alignItems="center" gap={8} paddingHorizontal={2}>
            <ShieldCheck size={14} color={brand.success} />
            <Paragraph fontSize={11} color="$slate500">
              Paiement sécurisé — vos identifiants ne transitent jamais par l'app.
            </Paragraph>
          </XStack>
        </Sheet.ScrollView>

        {/* CTA sticky */}
        <YStack padding={16} borderTopWidth={0.5} borderTopColor="$slate300">
          <Button
            size="$5"
            backgroundColor="$brand"
            color="white"
            fontWeight="900"
            borderRadius={14}
            disabled={!canSubmit || submitting}
            opacity={!canSubmit ? 0.55 : 1}
            onPress={handleSubmit}
            icon={submitting ? undefined : <Lock size={16} color="white" />}
          >
            {submitting ? 'Connexion au gateway…' : `Payer ${formatFcfa(amount)}`}
          </Button>
        </YStack>
      </Sheet.Frame>
    </Sheet>
  );
}
