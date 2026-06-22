import { AlertTriangle, ArrowLeft, ChevronRight } from '@tamagui/lucide-icons';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Stack, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, FlatList, Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useDisputes } from '@/hooks/useDisputes';
import { useSession } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';
import type { Dispute } from '@/types/dispute';

/**
 * Liste des litiges. Filter en cours / tous. Tap ouvre le détail.
 */
export default function DisputesList() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const [openOnly, setOpenOnly] = useState(false);
  const { data, isLoading, isError, error, refetch, isRefetching } = useDisputes(openOnly);

  if (!isAuthenticated) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={10}>
        <AlertTriangle size={36} color={brand.slate500} />
        <Paragraph fontSize={15} fontWeight="700" color="$slate900" textAlign="center">
          Connectez-vous pour voir vos litiges
        </Paragraph>
        <Pressable onPress={() => router.push('/(auth)/login')} hitSlop={6}>
          <XStack backgroundColor={brand.primary} paddingHorizontal={18} paddingVertical={10} borderRadius={10}>
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
        <YStack
          paddingTop={insets.top + 8}
          paddingHorizontal={14}
          paddingBottom={10}
          gap={10}
          borderBottomWidth={1}
          borderBottomColor="$slate300"
        >
          <XStack alignItems="center" gap={10}>
            <Pressable onPress={() => router.back()} hitSlop={8} accessibilityRole="button" accessibilityLabel="Retour">
              <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
                <ArrowLeft size={18} color={brand.slate700} />
              </YStack>
            </Pressable>
            <Paragraph fontSize={20} fontWeight="700" color="$slate900" flex={1}>
              Litiges
            </Paragraph>
          </XStack>
          <XStack gap={8}>
            <Tab label="Tous" active={!openOnly} onPress={() => setOpenOnly(false)} />
            <Tab label="En cours" active={openOnly} onPress={() => setOpenOnly(true)} />
          </XStack>
        </YStack>

        {isLoading ? (
          <YStack flex={1} alignItems="center" justifyContent="center">
            <ActivityIndicator />
          </YStack>
        ) : isError ? (
          <YStack padding="$5">
            <Paragraph color="$slate700">{extractApiErrorMessage(error)}</Paragraph>
          </YStack>
        ) : (
          <FlatList
            data={data ?? []}
            keyExtractor={(item) => item.id}
            contentContainerStyle={{ paddingHorizontal: 16, paddingVertical: 14, paddingBottom: insets.bottom + 24, gap: 10 }}
            onRefresh={() => refetch()}
            refreshing={isRefetching}
            ListEmptyComponent={
              <YStack padding="$6" alignItems="center" gap={6}>
                <AlertTriangle size={32} color={brand.slate500} />
                <Paragraph fontSize={14} fontWeight="700" color="$slate900">
                  Aucun litige
                </Paragraph>
                <Paragraph fontSize={12} color="$slate500" textAlign="center">
                  Si vous rencontrez un problème, ouvrez-en un depuis l'historique des paiements.
                </Paragraph>
              </YStack>
            }
            renderItem={({ item }) => (
              <DisputeRow dispute={item} onPress={() => router.push(`/disputes/${item.id}` as never)} />
            )}
          />
        )}
      </YStack>
    </>
  );
}

function Tab({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} hitSlop={4}>
      <XStack paddingHorizontal={14} paddingVertical={7} borderRadius={999} backgroundColor={active ? brand.slate900 : '$slate100'}>
        <Paragraph fontSize={13} fontWeight="700" color={active ? 'white' : '$slate700'}>{label}</Paragraph>
      </XStack>
    </Pressable>
  );
}

function DisputeRow({ dispute, onPress }: { dispute: Dispute; onPress: () => void }) {
  const status = statusFor(dispute.status);
  const relative = (() => {
    try {
      return formatDistanceToNow(new Date(dispute.created_at), { addSuffix: true, locale: fr });
    } catch {
      return '';
    }
  })();
  return (
    <Pressable onPress={onPress}>
      <YStack padding={14} borderRadius={12} borderWidth={1} borderColor="$slate300" gap={8} backgroundColor="$background">
        <XStack alignItems="center" justifyContent="space-between" gap={8}>
          <Paragraph fontSize={14} fontWeight="700" color="$slate900" flex={1} numberOfLines={1}>
            {dispute.subject}
          </Paragraph>
          <XStack alignItems="center" gap={4} paddingHorizontal={8} paddingVertical={3} borderRadius={999} backgroundColor={`${status.color}20`}>
            <Paragraph fontSize={11} fontWeight="700" color={status.color}>
              {status.label}
            </Paragraph>
          </XStack>
        </XStack>
        {dispute.reference && (
          <Paragraph fontSize={11} color="$slate500">Réf. {dispute.reference}</Paragraph>
        )}
        <XStack alignItems="center" justifyContent="space-between">
          <Paragraph fontSize={11} color="$slate500">Ouvert {relative}</Paragraph>
          <ChevronRight size={14} color={brand.slate500} />
        </XStack>
      </YStack>
    </Pressable>
  );
}

function statusFor(s: string): { label: string; color: string } {
  switch (s) {
    case 'open': return { label: 'Ouvert', color: brand.warning };
    case 'review': return { label: 'Examen', color: brand.info };
    case 'mediation': return { label: 'Médiation', color: brand.info };
    case 'resolved': return { label: 'Résolu', color: brand.success };
    case 'closed': return { label: 'Clos', color: brand.slate500 };
    default: return { label: s, color: brand.slate700 };
  }
}
