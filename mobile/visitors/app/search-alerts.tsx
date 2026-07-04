import {
  ArrowLeft,
  Bell,
  BellOff,
  Plus,
  Search,
  Trash2,
} from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Modal,
  Pressable,
  ScrollView,
  TextInput,
} from 'react-native';
import { Button, H2, Paragraph, Switch, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import {
  useCreateSearchAlert,
  useDeleteSearchAlert,
  useSearchAlerts,
  useUpdateSearchAlert,
} from '@/hooks/useSearchAlerts';
import { useSession } from '@/auth/SessionProvider';
import { useCurrency } from '@/hooks/useCurrency';
import { brand } from '@/theme/tokens';
import type { AlertFrequency, SearchAlert } from '@/types/search-alert';

/**
 * Saved search alerts — the user sets a named filter set (city, type,
 * price/surface ranges, keywords) and the backend pushes a notification
 * when fresh ads match. CRUD via the bottom-sheet `AlertEditor` modal.
 */
export default function SearchAlerts() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const { data, isLoading, isError, error, refetch, isRefetching } =
    useSearchAlerts();
  const create = useCreateSearchAlert();
  const update = useUpdateSearchAlert();
  const remove = useDeleteSearchAlert();

  const [editing, setEditing] = useState<SearchAlert | null>(null);
  const [creating, setCreating] = useState(false);

  if (!isAuthenticated) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={10}>
        <Bell size={36} color="$slate500" />
        <Paragraph fontSize={15} color="$slate900" fontWeight="700" textAlign="center">
          Connectez-vous pour gérer vos alertes
        </Paragraph>
        <Button backgroundColor="$brand" color="white" onPress={() => router.push('/(auth)/login')}>
          Se connecter
        </Button>
      </YStack>
    );
  }

  const handleToggle = (alert: SearchAlert) => {
    update.mutate({ id: alert.id, is_active: !alert.is_active });
  };

  const handleDelete = (alert: SearchAlert) => {
    Alert.alert('Supprimer l\'alerte', `Supprimer "${alert.label}" ?`, [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Supprimer',
        style: 'destructive',
        onPress: () => remove.mutate(alert.id),
      },
    ]);
  };

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
              <ArrowLeft size={18} color="$slate700" />
            </YStack>
          </Pressable>
          <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
            Alertes
          </H2>
          <Pressable onPress={() => setCreating(true)} hitSlop={6}>
            <YStack width={36} height={36} borderRadius={18} backgroundColor="$brand" alignItems="center" justifyContent="center">
              <Plus size={18} color="white" />
            </YStack>
          </Pressable>
        </XStack>

        {isLoading ? (
          <YStack flex={1} alignItems="center" justifyContent="center">
            <ActivityIndicator />
          </YStack>
        ) : isError ? (
          <YStack padding="$5"><Paragraph color="$slate700">{extractApiErrorMessage(error)}</Paragraph></YStack>
        ) : (
          <FlatList
            data={data ?? []}
            keyExtractor={(item) => item.id}
            contentContainerStyle={{ paddingHorizontal: 16, paddingVertical: 16, gap: 12, paddingBottom: insets.bottom + 24 }}
            onRefresh={() => refetch()}
            refreshing={isRefetching}
            ListEmptyComponent={
              <YStack padding="$6" alignItems="center" gap={6}>
                <Search size={36} color="$slate500" />
                <Paragraph fontSize={14} fontWeight="700" color="$slate900">
                  Aucune alerte
                </Paragraph>
                <Paragraph fontSize={12} color="$slate500" textAlign="center">
                  Créez une alerte pour être notifié des nouvelles annonces qui correspondent à vos critères.
                </Paragraph>
              </YStack>
            }
            renderItem={({ item }) => (
              <AlertRow
                alert={item}
                onToggle={() => handleToggle(item)}
                onDelete={() => handleDelete(item)}
                onEdit={() => setEditing(item)}
              />
            )}
          />
        )}

        <Modal
          visible={creating || editing != null}
          animationType="slide"
          presentationStyle="pageSheet"
          onRequestClose={() => {
            setCreating(false);
            setEditing(null);
          }}
        >
          <AlertEditor
            initial={editing}
            onClose={() => {
              setCreating(false);
              setEditing(null);
            }}
            onSave={async (payload) => {
              try {
                if (editing) {
                  await update.mutateAsync({ id: editing.id, ...payload });
                } else {
                  await create.mutateAsync(payload as Omit<SearchAlert, 'id' | 'created_at'>);
                }
                setCreating(false);
                setEditing(null);
              } catch (err) {
                Alert.alert('Erreur', extractApiErrorMessage(err));
              }
            }}
          />
        </Modal>
      </YStack>
    </>
  );
}

function AlertRow({
  alert,
  onToggle,
  onDelete,
  onEdit,
}: {
  alert: SearchAlert;
  onToggle: () => void;
  onDelete: () => void;
  onEdit: () => void;
}) {
  const { format } = useCurrency();
  const filters = alert.filters ?? {};
  const summary = [
    filters.city,
    filters.type,
    filters.max_price != null ? `≤ ${format(filters.max_price)}` : null,
    filters.bedrooms ? `${filters.bedrooms} ch.` : null,
  ]
    .filter(Boolean)
    .join(' · ');
  return (
    <Pressable onPress={onEdit}>
      <YStack
        padding={14}
        gap={8}
        borderRadius={14}
        borderWidth={1}
        borderColor={alert.is_active ? brand.primary : brand.slate300}
        backgroundColor={alert.is_active ? brand.primaryAlpha10 : '$background'}
      >
        <XStack alignItems="center" gap={10}>
          {alert.is_active ? (
            <Bell size={18} color={brand.primary} />
          ) : (
            <BellOff size={18} color="$slate500" />
          )}
          <YStack flex={1}>
            <Paragraph fontSize={15} fontWeight="700" color="$slate900">
              {alert.label}
            </Paragraph>
            {summary && (
              <Paragraph fontSize={12} color="$slate500" numberOfLines={1}>
                {summary}
              </Paragraph>
            )}
          </YStack>
          <Switch checked={alert.is_active} onCheckedChange={onToggle}>
            <Switch.Thumb animation="quick" />
          </Switch>
        </XStack>
        <XStack alignItems="center" justifyContent="space-between" gap={6}>
          <Paragraph fontSize={11} color="$slate500">
            {alert.frequency === 'immediate'
              ? 'Notification immédiate'
              : alert.frequency === 'daily'
                ? 'Récap quotidien'
                : 'Récap hebdomadaire'}
            {alert.match_count != null ? ` · ${alert.match_count} match` : ''}
          </Paragraph>
          <Pressable onPress={onDelete} hitSlop={6}>
            <Trash2 size={15} color={brand.danger} />
          </Pressable>
        </XStack>
      </YStack>
    </Pressable>
  );
}

function AlertEditor({
  initial,
  onClose,
  onSave,
}: {
  initial: SearchAlert | null;
  onClose: () => void;
  onSave: (payload: Partial<SearchAlert>) => Promise<void>;
}) {
  const insets = useSafeAreaInsets();
  const [label, setLabel] = useState(initial?.label ?? '');
  const [city, setCity] = useState(initial?.filters?.city ?? '');
  const [type, setType] = useState(initial?.filters?.type ?? '');
  const [maxPrice, setMaxPrice] = useState(
    initial?.filters?.max_price != null ? String(initial.filters.max_price) : '',
  );
  const [bedrooms, setBedrooms] = useState(
    initial?.filters?.bedrooms != null ? String(initial.filters.bedrooms) : '',
  );
  const [frequency, setFrequency] = useState<AlertFrequency>(initial?.frequency ?? 'daily');
  const [isActive, setIsActive] = useState(initial?.is_active ?? true);
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async () => {
    if (label.trim().length < 2) {
      Alert.alert('Nom requis', 'Donnez un nom à votre alerte.');
      return;
    }
    setSubmitting(true);
    await onSave({
      label: label.trim(),
      is_active: isActive,
      frequency,
      filters: {
        city: city.trim() === '' ? null : city.trim(),
        type: type.trim() === '' ? null : type.trim(),
        max_price: maxPrice.trim() === '' ? null : Number(maxPrice),
        bedrooms: bedrooms.trim() === '' ? null : Number(bedrooms),
      },
    });
    setSubmitting(false);
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <XStack
        paddingTop={insets.top + 8}
        paddingHorizontal={16}
        paddingBottom={12}
        alignItems="center"
        gap={12}
        borderBottomWidth={1}
        borderBottomColor="$slate300"
      >
        <Pressable onPress={onClose} hitSlop={6}>
          <Paragraph fontSize={15} color="$slate700">
            Annuler
          </Paragraph>
        </Pressable>
        <Paragraph fontSize={16} fontWeight="700" color="$slate900" flex={1} textAlign="center">
          {initial ? 'Modifier l\'alerte' : 'Nouvelle alerte'}
        </Paragraph>
        <Pressable onPress={handleSubmit} disabled={submitting} hitSlop={6}>
          <Paragraph fontSize={15} fontWeight="700" color={brand.primary}>
            {submitting ? '…' : 'Enregistrer'}
          </Paragraph>
        </Pressable>
      </XStack>

      <ScrollView
        contentContainerStyle={{
          paddingHorizontal: 20,
          paddingTop: 16,
          paddingBottom: insets.bottom + 24,
          gap: 14,
        }}
        showsVerticalScrollIndicator={false}
      >
        <Field label="Nom de l'alerte" value={label} onChange={setLabel} placeholder="Ex. Appart 2 ch. Douala" />
        <Field label="Ville" value={city} onChange={setCity} placeholder="Douala, Yaoundé…" />
        <Field label="Type de bien" value={type} onChange={setType} placeholder="Appartement, studio…" />
        <XStack gap={12}>
          <YStack flex={1}>
            <Field label="Prix max" value={maxPrice} onChange={setMaxPrice} placeholder="500000" keyboardType="numeric" />
          </YStack>
          <YStack flex={1}>
            <Field label="Chambres" value={bedrooms} onChange={setBedrooms} placeholder="2" keyboardType="numeric" />
          </YStack>
        </XStack>

        <YStack gap={6}>
          <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">
            Fréquence
          </Paragraph>
          <XStack gap={8}>
            {(['immediate', 'daily', 'weekly'] as const).map((f) => (
              <Pressable key={f} onPress={() => setFrequency(f)} style={{ flex: 1 }}>
                <YStack
                  paddingVertical={10}
                  borderRadius={10}
                  borderWidth={1}
                  borderColor={frequency === f ? brand.primary : brand.slate300}
                  backgroundColor={frequency === f ? brand.primaryAlpha10 : '$background'}
                  alignItems="center"
                >
                  <Paragraph fontSize={12} fontWeight="700" color={frequency === f ? brand.primary : '$slate700'}>
                    {f === 'immediate' ? 'Immédiate' : f === 'daily' ? 'Quotidienne' : 'Hebdo'}
                  </Paragraph>
                </YStack>
              </Pressable>
            ))}
          </XStack>
        </YStack>

        <XStack alignItems="center" padding={12} borderRadius={10} borderWidth={1} borderColor="$slate300" gap={10}>
          <Paragraph fontSize={14} fontWeight="600" color="$slate900" flex={1}>
            Alerte active
          </Paragraph>
          <Switch checked={isActive} onCheckedChange={setIsActive}>
            <Switch.Thumb animation="quick" />
          </Switch>
        </XStack>
      </ScrollView>
    </YStack>
  );
}

function Field({
  label,
  value,
  onChange,
  placeholder,
  keyboardType,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  keyboardType?: 'default' | 'numeric';
}) {
  return (
    <YStack gap={6}>
      <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">
        {label}
      </Paragraph>
      <TextInput
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        placeholderTextColor={brand.slate500}
        keyboardType={keyboardType ?? 'default'}
        style={{
          borderWidth: 1,
          borderColor: brand.slate300,
          borderRadius: 12,
          paddingHorizontal: 14,
          paddingVertical: 12,
          fontSize: 15,
          color: brand.slate900,
        }}
      />
    </YStack>
  );
}
