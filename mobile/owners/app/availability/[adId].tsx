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
import type { WeekdaySlug } from '@/types/availability';

const WEEKDAYS: { value: WeekdaySlug; label: string }[] = [
  { value: 'monday', label: 'Lun' },
  { value: 'tuesday', label: 'Mar' },
  { value: 'wednesday', label: 'Mer' },
  { value: 'thursday', label: 'Jeu' },
  { value: 'friday', label: 'Ven' },
  { value: 'saturday', label: 'Sam' },
  { value: 'sunday', label: 'Dim' },
];

const WEEKDAY_LABELS: Record<string, string> = {
  monday: 'Lun',
  tuesday: 'Mar',
  wednesday: 'Mer',
  thursday: 'Jeu',
  friday: 'Ven',
  saturday: 'Sam',
  sunday: 'Dim',
};

/**
 * Date du jour au format `YYYY-MM-DD` en heure LOCALE (exigé par
 * `starts_on: after_or_equal:today`). `toISOString()` renverrait la date
 * UTC → en soirée GMT+1 elle peut être « demain » et provoquer un 422.
 */
function todayIso(): string {
  const d = new Date();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}

export default function AvailabilityScreen() {
  const { adId } = useLocalSearchParams<{ adId: string }>();
  const { isAuthenticated } = useSession();
  const { data: schedules = [], isLoading, isRefetching, refetch } = useAdAvailability(adId, isAuthenticated);
  const create = useCreateAvailability(adId ?? '');
  const remove = useDeleteAvailability(adId ?? '');

  const [name, setName] = useState('Visites en semaine');
  const [days, setDays] = useState<WeekdaySlug[]>(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
  const [start, setStart] = useState('09:00');
  const [end, setEnd] = useState('17:00');
  const [slotDuration, setSlotDuration] = useState('30');

  const toggleDay = (d: WeekdaySlug) => {
    setDays((prev) => (prev.includes(d) ? prev.filter((x) => x !== d) : [...prev, d]));
  };

  const onAdd = () => {
    if (!name.trim()) {
      Alert.alert('Nom requis', 'Donnez un nom à ce planning (ex : « Visites en semaine »).');
      return;
    }
    if (!start || !end) {
      return;
    }
    if (days.length === 0) {
      Alert.alert('Jours requis', 'Sélectionnez au moins un jour de la semaine.');
      return;
    }
    const duration = Number.parseInt(slotDuration, 10);
    create.mutate(
      {
        name: name.trim(),
        starts_on: todayIso(),
        recurrence: 'weekly',
        recurrence_days: days,
        periods: [{ starts_at: start, ends_at: end }],
        slot_duration: Number.isFinite(duration) ? Math.min(240, Math.max(15, duration)) : 30,
      },
      { onError: (err) => Alert.alert('Erreur', extractApiErrorMessage(err)) },
    );
  };

  const onDelete = (id: string) => {
    Alert.alert('Supprimer', 'Supprimer ce planning de visite ?', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Supprimer', style: 'destructive', onPress: () => remove.mutate(id) },
    ]);
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Créneaux de visite" subtitle="Définissez vos plannings hebdomadaires" />

      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 14 }}
        refreshControl={<RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />}
      >
        <YStack
          padding={14}
          gap={12}
          borderRadius={14}
          borderWidth={1}
          borderColor="$slate300"
          backgroundColor={brand.primaryAlpha10}
        >
          <Paragraph fontSize={13} fontWeight="800" color="$slate900">
            Nouveau planning
          </Paragraph>

          <YStack gap={6}>
            <Paragraph fontSize={12} fontWeight="700" color="$slate700">
              Nom
            </Paragraph>
            <Input value={name} onChangeText={setName} placeholder="Visites en semaine" />
          </YStack>

          <YStack gap={6}>
            <Paragraph fontSize={12} fontWeight="700" color="$slate700">
              Jours
            </Paragraph>
            <XStack gap={6} flexWrap="wrap">
              {WEEKDAYS.map((d) => {
                const isSel = days.includes(d.value);
                return (
                  <Button
                    key={d.value}
                    size="$2"
                    chromeless
                    borderRadius={999}
                    backgroundColor={isSel ? '$brand' : '$slate100'}
                    onPress={() => toggleDay(d.value)}
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
            <YStack width={92} gap={6}>
              <Paragraph fontSize={12} fontWeight="700" color="$slate700">
                Min/visite
              </Paragraph>
              <Input value={slotDuration} onChangeText={setSlotDuration} keyboardType="number-pad" placeholder="30" />
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
            {create.isPending ? 'Ajout…' : 'Ajouter le planning'}
          </Button>
        </YStack>

        {isLoading ? (
          <YStack height={200} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : schedules.length === 0 ? (
          <YStack height={200}>
            <EmptyState title="Aucun planning" hint="Ajoutez votre premier planning de visite ci-dessus." />
          </YStack>
        ) : (
          <YStack gap={8}>
            {schedules.map((s) => {
              const daysLabel = (s.frequency_config?.days ?? [])
                .map((d) => WEEKDAY_LABELS[d] ?? d)
                .join(', ');
              const period = s.periods?.[0];
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
                      {s.name}
                    </Paragraph>
                    <Paragraph fontSize={12} color="$slate500">
                      {daysLabel ? `${daysLabel} · ` : ''}
                      {period ? `${period.starts_at} → ${period.ends_at}` : '—'}
                      {s.slot_duration ? ` · ${s.slot_duration} min/visite` : ''}
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
