import { ArrowLeft, Calendar, CheckCircle2, Clock, MapPin, X } from '@tamagui/lucide-icons';
import { format, isAfter } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Stack, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { ActivityIndicator, Alert, FlatList, Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useCancelReservation, useMyReservations } from '@/hooks/useReservations';
import { useSession } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';
import type { Reservation } from '@/types/reservation';

type Tab = 'upcoming' | 'past' | 'all';

/**
 * Mes réservations de visites. Trois tabs : à venir, passées, toutes.
 * Annulation depuis un Alert natif avec confirmation (le backend
 * accepte une raison optionnelle, on la passe vide pour l'instant).
 */
export default function Reservations() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const { data, isLoading, isError, error, refetch, isRefetching } = useMyReservations();
  const cancel = useCancelReservation();
  const [tab, setTab] = useState<Tab>('upcoming');

  const filtered = useMemo(() => {
    const now = new Date();
    const list = data ?? [];
    if (tab === 'all') return list;
    return list.filter((r) => {
      const start = new Date(r.starts_at);
      return tab === 'upcoming' ? isAfter(start, now) : !isAfter(start, now);
    });
  }, [data, tab]);

  if (!isAuthenticated) {
    return <SignInWall onSignIn={() => router.push('/(auth)/login')} />;
  }

  const handleCancel = (r: Reservation) => {
    Alert.alert('Annuler cette visite ?', `Visite prévue ${format(new Date(r.starts_at), "EEEE d MMMM 'à' HH'h'mm", { locale: fr })}.`, [
      { text: 'Non', style: 'cancel' },
      {
        text: 'Annuler',
        style: 'destructive',
        onPress: async () => {
          try {
            await cancel.mutateAsync({ id: r.id });
          } catch (err) {
            Alert.alert('Erreur', extractApiErrorMessage(err));
          }
        },
      },
    ]);
  };

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
                <ArrowLeft size={18} color="$slate700" />
              </YStack>
            </Pressable>
            <Paragraph fontSize={20} fontWeight="700" color="$slate900" flex={1}>
              Mes réservations
            </Paragraph>
          </XStack>
          <XStack gap={8}>
            <Tab label="À venir" active={tab === 'upcoming'} onPress={() => setTab('upcoming')} />
            <Tab label="Passées" active={tab === 'past'} onPress={() => setTab('past')} />
            <Tab label="Toutes" active={tab === 'all'} onPress={() => setTab('all')} />
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
            data={filtered}
            keyExtractor={(item) => item.id}
            contentContainerStyle={{ paddingHorizontal: 16, paddingVertical: 14, paddingBottom: insets.bottom + 24, gap: 12 }}
            onRefresh={() => refetch()}
            refreshing={isRefetching}
            ListEmptyComponent={
              <YStack padding="$6" alignItems="center" gap={6}>
                <Calendar size={32} color="$slate500" />
                <Paragraph fontSize={14} fontWeight="700" color="$slate900">
                  Aucune réservation
                </Paragraph>
                <Paragraph fontSize={12} color="$slate500" textAlign="center">
                  Vos visites planifiées apparaîtront ici.
                </Paragraph>
              </YStack>
            }
            renderItem={({ item }) => <ReservationCard r={item} onCancel={() => handleCancel(item)} />}
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

function ReservationCard({ r, onCancel }: { r: Reservation; onCancel: () => void }) {
  const start = new Date(r.starts_at);
  const dateLabel = format(start, "EEEE d MMMM", { locale: fr });
  const timeLabel = format(start, "HH'h'mm", { locale: fr });
  const status = statusFor(r.status);

  return (
    <YStack padding={14} borderRadius={14} borderWidth={1} borderColor="$slate300" gap={10} backgroundColor="$background">
      <XStack alignItems="center" justifyContent="space-between" gap={8}>
        <XStack alignItems="center" gap={6}>
          {status.icon}
          <Paragraph fontSize={12} fontWeight="800" color={status.color} textTransform="uppercase">
            {status.label}
          </Paragraph>
        </XStack>
        {(r.status === 'pending' || r.status === 'confirmed') && (
          <Pressable onPress={onCancel} hitSlop={6}>
            <XStack alignItems="center" gap={4}>
              <X size={13} color={brand.danger} />
              <Paragraph fontSize={12} fontWeight="700" color={brand.danger}>Annuler</Paragraph>
            </XStack>
          </Pressable>
        )}
      </XStack>
      {r.ad?.title && (
        <Paragraph fontSize={15} fontWeight="700" color="$slate900" numberOfLines={1}>{r.ad.title}</Paragraph>
      )}
      {(r.ad?.quarter?.name || r.ad?.quarter?.city_name) && (
        <XStack alignItems="center" gap={6}>
          <MapPin size={13} color="$slate500" />
          <Paragraph fontSize={13} color="$slate500" numberOfLines={1}>
            {[r.ad?.quarter?.name, r.ad?.quarter?.city_name].filter(Boolean).join(', ')}
          </Paragraph>
        </XStack>
      )}
      <XStack alignItems="center" gap={10}>
        <Calendar size={14} color="$slate700" />
        <Paragraph fontSize={13.5} fontWeight="600" color="$slate900">
          {dateLabel} · {timeLabel}
        </Paragraph>
      </XStack>
    </YStack>
  );
}

function statusFor(status: string): { icon: React.ReactNode; label: string; color: string } {
  switch (status) {
    case 'confirmed':
      return { icon: <CheckCircle2 size={14} color={brand.success} />, label: 'Confirmée', color: brand.success };
    case 'completed':
      return { icon: <CheckCircle2 size={14} color="$slate500" />, label: 'Terminée', color: brand.slate500 };
    case 'cancelled':
      return { icon: <X size={14} color={brand.danger} />, label: 'Annulée', color: brand.danger };
    case 'expired':
      return { icon: <Clock size={14} color="$slate500" />, label: 'Expirée', color: brand.slate500 };
    case 'no_show':
      return { icon: <X size={14} color={brand.warning} />, label: 'Absence', color: brand.warning };
    default:
      return { icon: <Clock size={14} color={brand.warning} />, label: 'En attente', color: brand.warning };
  }
}

function SignInWall({ onSignIn }: { onSignIn: () => void }) {
  return (
    <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={10}>
      <Calendar size={36} color="$slate500" />
      <Paragraph fontSize={15} fontWeight="700" color="$slate900" textAlign="center">
        Connectez-vous pour voir vos réservations
      </Paragraph>
      <Pressable onPress={onSignIn} hitSlop={6}>
        <XStack backgroundColor={brand.primary} paddingHorizontal={18} paddingVertical={10} borderRadius={10}>
          <Paragraph color="white" fontWeight="700">Se connecter</Paragraph>
        </XStack>
      </Pressable>
    </YStack>
  );
}
