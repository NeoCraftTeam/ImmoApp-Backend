import { useState } from 'react';
import { Plus, Trash2 } from '@tamagui/lucide-icons';
import { useLocalSearchParams } from 'expo-router';
import { Alert, RefreshControl, ScrollView } from 'react-native';
import { Button, Input, Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import {
  useAdAvailability,
  useCreateAvailability,
  useDeleteAvailability,
} from '@/hooks/useAvailability';
import { brand } from '@/theme/tokens';
import type { DayOfWeek } from '@/types/availability';

const DAYS: { value: DayOfWeek; label: string }[] = [
  { value: 1, label: 'Lundi' },
  { value: 2, label: 'Mardi' },
  { value: 3, label: 'Mercredi' },
  { value: 4, label: 'Jeudi' },
  { value: 5, label: 'Vendredi' },
  { value: 6, label: 'Samedi' },
  { value: 0, label: 'Dimanche' },
];

export default function AvailabilityScreen() {
  const { adId } = useLocalSearchParams<{ adId: string }>();
  const { isAuthenticated } = useSession();
  const { data: slots = [], isLoading, isRefetching, refetch } = useAdAvailability(adId, isAuthenticated);
  const create = useCreateAvailability(adId ?? '');
  const remove = useDeleteAvailability(adId ?? '');

  const [day, setDay] = useState<DayOfWeek>(1);
  const [start, setStart] = useState('09:00');
  const [end, setEnd] = useState('17:00');

  const onAdd = () => {
    if (!start || !end) return;
    create.mutate(
      { day_of_week: day, start_time: start, end_time: end, slot_minutes: 30 },
      { onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)) },
    );
  };

  const onDelete = (id: string) => {
    Alert.alert('Supprimer', 'Supprimer ce créneau ?', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Supprimer', style: 'destructive', onPress: () => remove.mutate(id) },
    ]);
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Créneaux de visite" subtitle="Définissez vos plages hebdomadaires" />

      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 14 }}
        refreshControl={<RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />}
      >
        <YStack
          padding={14}
          gap={10}
          borderRadius={14}
          borderWidth={1}
          borderColor="$slate300"
          backgroundColor={brand.primaryAlpha10}
        >
          <Paragraph fontSize={13} fontWeight="800" color="$slate900">
            Ajouter un créneau
          </Paragraph>
          <YStack gap={6}>
            <Paragraph fontSize={12} fontWeight="700" color="$slate700">
              Jour
            </Paragraph>
            <XStack gap={6} flexWrap="wrap">
              {DAYS.map((d) => {
                const isSel = day === d.value;
                return (
                  <Button
                    key={d.value}
                    size="$2"
                    chromeless
                    borderRadius={999}
                    backgroundColor={isSel ? '$brand' : '$slate100'}
                    onPress={() => setDay(d.value)}
                    paddingHorizontal={12}
                  >
                    <Paragraph fontSize={12} fontWeight="700" color={isSel ? 'white' : '$slate700'}>
                      {d.label}
                    </Paragraph>
                  </Button>
                );
              })}
            </XStack>
          </YStack>

          <XStack gap={10}>
            <YStack flex={1} gap={6}>
              <Paragraph fontSize={12} fontWeight="700" color="$slate700">
                Début (HH:MM)
              </Paragraph>
              <Input value={start} onChangeText={setStart} placeholder="09:00" />
            </YStack>
            <YStack flex={1} gap={6}>
              <Paragraph fontSize={12} fontWeight="700" color="$slate700">
                Fin (HH:MM)
              </Paragraph>
              <Input value={end} onChangeText={setEnd} placeholder="17:00" />
            </YStack>
          </XStack>

          <Button
            size="$3"
            backgroundColor="$brand"
            color="white"
            fontWeight="800"
            borderRadius={10}
            onPress={onAdd}
            disabled={create.isPending}
            icon={<Plus size={14} color="white" />}
          >
            {create.isPending ? 'Ajout…' : 'Ajouter'}
          </Button>
        </YStack>

        {isLoading ? (
          <YStack height={200} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : slots.length === 0 ? (
          <YStack height={200}>
            <EmptyState title="Aucun créneau" hint="Ajoutez votre premier créneau ci-dessus." />
          </YStack>
        ) : (
          <YStack gap={8}>
            {slots.map((s) => {
              const dayLabel = DAYS.find((d) => d.value === s.day_of_week)?.label ?? `Jour ${s.day_of_week}`;
              return (
                <XStack
                  key={s.id}
                  padding={12}
                  gap={10}
                  borderRadius={12}
                  borderWidth={1}
                  borderColor="$slate300"
                  alignItems="center"
                >
                  <YStack flex={1} gap={2}>
                    <Paragraph fontSize={13.5} fontWeight="700">
                      {dayLabel}
                    </Paragraph>
                    <Paragraph fontSize={12} color="$slate500">
                      {s.start_time} → {s.end_time}
                      {s.slot_minutes ? ` · ${s.slot_minutes} min/visite` : ''}
                    </Paragraph>
                  </YStack>
                  <Button size="$2" chromeless onPress={() => onDelete(s.id)} icon={<Trash2 size={14} color={brand.danger} />} />
                </XStack>
              );
            })}
          </YStack>
        )}
      </ScrollView>
    </YStack>
  );
}
