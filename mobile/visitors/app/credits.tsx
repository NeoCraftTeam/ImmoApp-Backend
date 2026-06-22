import {
  ArrowLeft,
  CheckCircle2,
  Clock,
  CreditCard,
  Wallet,
  XCircle,
} from '@tamagui/lucide-icons';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Stack, useRouter } from 'expo-router';
import { ActivityIndicator, FlatList, Linking, Pressable } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useCreditsBalance, usePayments } from '@/hooks/usePayments';
import { useSession } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';
import type { PaymentTransaction } from '@/types/payment';

/**
 * Credits + payments dashboard. Top card surfaces the user's balance
 * (live via `useCreditsBalance`), with a CTA that deep-links to the
 * web checkout — adding the mobile payment provider is out of scope
 * for this iteration but the user still has a clear path to top up.
 * Below: paginated transaction history.
 */
export default function CreditsScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const balance = useCreditsBalance();
  const payments = usePayments();

  if (!isAuthenticated) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={10}>
        <Wallet size={36} color={brand.slate500} />
        <Paragraph fontSize={15} fontWeight="700" color="$slate900" textAlign="center">
          Connectez-vous pour voir vos crédits
        </Paragraph>
        <Pressable onPress={() => router.push('/(auth)/login')}>
          <XStack backgroundColor="$brand" paddingHorizontal={18} paddingVertical={10} borderRadius={10}>
            <Paragraph color="white" fontWeight="700">Se connecter</Paragraph>
          </XStack>
        </Pressable>
      </YStack>
    );
  }

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$background">
        <XStack
          paddingTop={insets.top + 8}
          paddingHorizontal={14}
          paddingBottom={10}
          alignItems="center"
          gap={10}
          borderBottomWidth={1}
          borderBottomColor="$slate300"
        >
          <Pressable onPress={() => router.back()} hitSlop={8} accessibilityRole="button" accessibilityLabel="Retour">
            <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
              <ArrowLeft size={18} color={brand.slate700} />
            </YStack>
          </Pressable>
          <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
            Crédits
          </H2>
        </XStack>

        <FlatList
          data={payments.data ?? []}
          keyExtractor={(item) => item.id}
          contentContainerStyle={{ paddingHorizontal: 16, paddingTop: 18, paddingBottom: insets.bottom + 24, gap: 10 }}
          ListHeaderComponent={
            <YStack gap={14} marginBottom={12}>
              <YStack
                padding={18}
                borderRadius={16}
                backgroundColor="$slate900"
                gap={12}
              >
                <XStack alignItems="center" gap={8}>
                  <Wallet size={18} color="white" />
                  <Paragraph fontSize={13} color="rgba(255,255,255,0.8)" fontWeight="700">
                    Solde de crédits
                  </Paragraph>
                </XStack>
                <Paragraph fontSize={32} fontWeight="800" color="white">
                  {balance.isLoading ? '—' : `${balance.data?.toLocaleString('fr-FR') ?? 0} pts`}
                </Paragraph>
                <Pressable
                  onPress={() => Linking.openURL('https://keyhome.app/credits')}
                  hitSlop={6}
                >
                  <XStack
                    backgroundColor={brand.primary}
                    paddingHorizontal={16}
                    paddingVertical={11}
                    borderRadius={12}
                    alignItems="center"
                    gap={8}
                    alignSelf="flex-start"
                  >
                    <CreditCard size={16} color="white" />
                    <Paragraph color="white" fontWeight="700">
                      Recharger
                    </Paragraph>
                  </XStack>
                </Pressable>
                <Paragraph fontSize={11} color="rgba(255,255,255,0.65)" lineHeight={16}>
                  Le rechargement se fait pour l'instant sur la version web. Vos crédits débloquent
                  l'accès aux annonces et au contact direct des bailleurs.
                </Paragraph>
              </YStack>

              <Paragraph fontSize={15} fontWeight="700" color="$slate900">
                Historique des transactions
              </Paragraph>
            </YStack>
          }
          ListEmptyComponent={
            payments.isLoading ? (
              <YStack alignItems="center" padding={20}>
                <ActivityIndicator />
              </YStack>
            ) : payments.isError ? (
              <Paragraph color="$slate700">{extractApiErrorMessage(payments.error)}</Paragraph>
            ) : (
              <YStack padding={20} alignItems="center" gap={6}>
                <Paragraph fontSize={13} color="$slate500" textAlign="center">
                  Aucune transaction pour le moment.
                </Paragraph>
              </YStack>
            )
          }
          renderItem={({ item }) => <TxRow tx={item} />}
        />
      </YStack>
    </>
  );
}

function TxRow({ tx }: { tx: PaymentTransaction }) {
  const status = statusFor(tx.status);
  const relative = (() => {
    try {
      return formatDistanceToNow(new Date(tx.created_at), { addSuffix: true, locale: fr });
    } catch {
      return '';
    }
  })();

  return (
    <XStack
      padding={14}
      borderRadius={12}
      borderWidth={1}
      borderColor="$slate300"
      backgroundColor="$background"
      alignItems="center"
      gap={12}
    >
      <YStack
        width={36}
        height={36}
        borderRadius={18}
        backgroundColor={`${status.color}20`}
        alignItems="center"
        justifyContent="center"
      >
        {status.icon}
      </YStack>
      <YStack flex={1} gap={2}>
        <Paragraph fontSize={14} fontWeight="700" color="$slate900" numberOfLines={1}>
          {tx.description ?? 'Transaction'}
        </Paragraph>
        <Paragraph fontSize={12} color="$slate500">
          {relative} · {tx.provider ?? 'KeyHome'}
        </Paragraph>
      </YStack>
      <YStack alignItems="flex-end" gap={2}>
        <Paragraph fontSize={14} fontWeight="800" color="$slate900">
          {tx.amount.toLocaleString('fr-FR')} {tx.currency ?? 'XAF'}
        </Paragraph>
        <Paragraph fontSize={11} fontWeight="700" color={status.color}>
          {status.label}
        </Paragraph>
      </YStack>
    </XStack>
  );
}

function statusFor(s: string): { icon: React.ReactNode; color: string; label: string } {
  if (s === 'success') {
    return { icon: <CheckCircle2 size={18} color={brand.success} />, color: brand.success, label: 'Réussi' };
  }
  if (s === 'failed') {
    return { icon: <XCircle size={18} color={brand.danger} />, color: brand.danger, label: 'Échoué' };
  }
  if (s === 'refunded') {
    return { icon: <CheckCircle2 size={18} color={brand.info} />, color: brand.info, label: 'Remboursé' };
  }
  return { icon: <Clock size={18} color={brand.warning} />, color: brand.warning, label: 'En attente' };
}
