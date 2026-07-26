import { CreditCard, Receipt } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { SavedCardsSection } from '@/components/payments/SavedCardsSection';
import { ScreenHeader } from '@/components/ScreenHeader';
import { usePayments } from '@/hooks/usePayments';
import { brand } from '@/theme/tokens';
import { formatDateTime, formatFcfa } from '@/utils/format';
import type { PaymentEntry, PaymentStatus } from '@/types/payment';

const STATUS_COLOR: Record<PaymentStatus, string> = {
  succeeded: brand.success,
  success: brand.success,
  pending: brand.warning,
  failed: brand.danger,
  refunded: brand.secondary,
  cancelled: brand.slate500,
  requires_action: brand.warning,
};

const STATUS_LABEL: Record<PaymentStatus, string> = {
  succeeded: 'Réussi',
  success: 'Réussi',
  pending: 'En attente',
  failed: 'Échec',
  refunded: 'Remboursé',
  cancelled: 'Annulé',
  requires_action: 'Action requise',
};

function PaymentRow({ p, onVerify }: { p: PaymentEntry; onVerify?: (txRef: string) => void }) {
  const color = STATUS_COLOR[p.status] ?? brand.slate500;
  // Une transaction en attente avec une référence peut être re-vérifiée
  // (tap) — l'écran de résultat tranche via le poll public-status. Utile
  // quand l'utilisateur a fermé l'onglet avant la confirmation.
  const canVerify = p.status === 'pending' && Boolean(p.tx_ref) && Boolean(onVerify);

  const body = (
    <XStack
      padding={14}
      gap={12}
      borderRadius={14}
      borderWidth={1}
      borderColor="$slate300"
      alignItems="center"
      backgroundColor="$background"
    >
      <YStack
        width={44}
        height={44}
        borderRadius={22}
        backgroundColor={`${color}1A`}
        alignItems="center"
        justifyContent="center"
      >
        <CreditCard size={20} color={color} />
      </YStack>
      <YStack flex={1} gap={3}>
        <Paragraph fontSize={14} fontWeight="700" color="$slate900" numberOfLines={1}>
          {p.description ?? p.tx_ref}
        </Paragraph>
        {(p.payment_method_label || p.payment_method_detail) ? (
          <Paragraph fontSize={11.5} color="$slate500" numberOfLines={2}>
            {[p.payment_method_label, p.payment_method_detail].filter(Boolean).join(' · ')}
          </Paragraph>
        ) : null}
        <Paragraph fontSize={11.5} color="$slate500">
          {formatDateTime(p.created_at)}
        </Paragraph>
        {canVerify ? (
          <Paragraph fontSize={11.5} fontWeight="700" color={brand.primary}>
            Toucher pour vérifier le statut
          </Paragraph>
        ) : null}
      </YStack>
      <YStack alignItems="flex-end" gap={3}>
        <Paragraph fontSize={14} fontWeight="800" color="$slate900">
          {formatFcfa(p.amount)}
        </Paragraph>
        <Paragraph fontSize={11} fontWeight="700" color={color}>
          {p.status_label ?? STATUS_LABEL[p.status] ?? p.status}
        </Paragraph>
      </YStack>
    </XStack>
  );

  if (!canVerify) {
    return body;
  }

  return (
    <Pressable
      onPress={() => onVerify?.(p.tx_ref)}
      accessibilityRole="button"
      accessibilityLabel="Vérifier le statut du paiement"
    >
      {body}
    </Pressable>
  );
}

export default function PaymentsScreen() {
  const router = useRouter();
  const { isAuthenticated } = useSession();
  const { data, isLoading, isRefetching, refetch } = usePayments(isAuthenticated);
  const list = data?.data ?? [];

  const verifyPending = (txRef: string) => {
    router.push({ pathname: '/payment-success', params: { tx_ref: txRef } } as never);
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Historique des paiements" />
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 16 }}
        refreshControl={
          <RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />
        }
      >
        <SavedCardsSection enabled={isAuthenticated} />

        <Paragraph fontSize={16} fontWeight="900" color="$slate900">
          Historique
        </Paragraph>

        {isLoading ? (
          <YStack height={320} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : list.length === 0 ? (
          <YStack height={320}>
            <EmptyState
              icon={<Receipt size={28} color={brand.primary} />}
              title="Aucun paiement"
              hint="Vos paiements (abonnements, boosts, services pro) apparaîtront ici."
            />
          </YStack>
        ) : (
          list.map((p) => <PaymentRow key={p.id} p={p} onVerify={verifyPending} />)
        )}
      </ScrollView>
    </YStack>
  );
}
