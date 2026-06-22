import { Lock, ShieldCheck, X } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Alert, Pressable } from 'react-native';
import { Button, Input, Paragraph, Sheet, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/extract-error';
import { PaymentMethodPicker } from '@/components/payments/PaymentMethodPicker';
import {
  useInitiatePayment,
  usePaymentMethods,
} from '@/hooks/usePayments';
import { openHostedCheckout } from '@/services/checkout';
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
  const { data: methods = [], isLoading: methodsLoading } = usePaymentMethods(open);
  const initiate = useInitiatePayment();

  const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
  const [phone, setPhone] = useState<string>('');
  const [submitting, setSubmitting] = useState(false);

  // Auto-pick la première méthode si une seule est dispo
  useEffect(() => {
    if (open && !selectedMethod && methods.length > 0) {
      setSelectedMethod(methods.find((m) => m.is_default) ?? methods[0] ?? null);
    }
  }, [open, methods, selectedMethod]);

  // Reset à la fermeture
  useEffect(() => {
    if (!open) {
      setSelectedMethod(null);
      setPhone('');
      setSubmitting(false);
    }
  }, [open]);

  const requiresPhone = useMemo(
    () => Boolean(selectedMethod?.requires_phone || selectedMethod?.channel === 'mobile_money'),
    [selectedMethod],
  );

  const canSubmit = Boolean(selectedMethod) && (!requiresPhone || phone.trim().length >= 8);

  const handleSubmit = async () => {
    if (!selectedMethod) return;
    setSubmitting(true);
    trackEvent('payment.sheet.submit', { purpose, gateway: selectedMethod.gateway });

    try {
      const payload: InitiatePaymentInput = {
        amount,
        type: purpose,
        payment_method: selectedMethod.code,
        phone_number: requiresPhone ? phone.trim() : undefined,
        ...extraPayload,
      };
      const init = await initiate.mutateAsync(payload);
      const link = init.payment_link ?? init.payment_url;
      const txRef = init.tx_ref;

      if (!link) {
        Alert.alert('Erreur', 'Le gateway de paiement n’a pas renvoyé d’URL.');
        setSubmitting(false);
        return;
      }

      onOpenChange(false); // fermer le sheet avant d'ouvrir le browser
      const result = await openHostedCheckout(link, txRef);

      if (result.cancelled) {
        Alert.alert('Paiement annulé', 'Vous avez fermé la fenêtre de paiement.');
        return;
      }
      if (result.error) {
        Alert.alert('Erreur', result.error.message);
        return;
      }
      const finalRef = result.txRef ?? txRef;
      onSuccess?.(finalRef);
      router.push({
        pathname: '/payment-success',
        params: { tx_ref: finalRef },
      } as never);
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
