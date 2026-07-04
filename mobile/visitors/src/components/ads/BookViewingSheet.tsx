import { CheckCircle2, X } from '@tamagui/lucide-icons';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { useState } from 'react';
import { ActivityIndicator, Alert, Modal, Pressable, ScrollView, TextInput } from 'react-native';
import { Button, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useCreateReservation, useViewingSlots, type ViewingSlot } from '@/hooks/useReservations';
import { brand } from '@/theme/tokens';

interface Props {
  adId: string;
  open: boolean;
  onClose: () => void;
  onBooked?: () => void;
}

/**
 * Bottom sheet pour réserver un créneau de visite. Liste les slots
 * disponibles regroupés par jour. Une note optionnelle se joint à la
 * demande. Confirmation success-screen avant fermeture.
 */
export function BookViewingSheet({ adId, open, onClose, onBooked }: Props) {
  const insets = useSafeAreaInsets();
  const slots = useViewingSlots(open ? adId : undefined);
  const reserve = useCreateReservation(adId);
  const [selected, setSelected] = useState<ViewingSlot | null>(null);
  const [notes, setNotes] = useState('');
  const [done, setDone] = useState(false);

  const slotKey = (s: ViewingSlot): string => `${s.date}T${s.starts_at}`;

  const handleClose = () => {
    setSelected(null);
    setNotes('');
    setDone(false);
    onClose();
  };

  const handleConfirm = async () => {
    // Guard double-tap : une visite reservee 2x cree 2 entries en
    // backend, le bailleur recoit 2 notifications, le creneau peut
    // se faire booker par un autre user entre les 2 calls. C'est un
    // P0 sur les flows de booking (conflit de creneau, support
    // tickets, no-show fantome).
    if (reserve.isPending) return;
    if (!selected) {
      Alert.alert('Créneau requis', 'Choisissez un créneau disponible.');
      return;
    }
    try {
      await reserve.mutateAsync({
        slot_date: selected.date,
        slot_starts_at: selected.starts_at,
        slot_ends_at: selected.ends_at,
        client_message: notes.trim() || undefined,
      });
      setDone(true);
      onBooked?.();
    } catch (err) {
      Alert.alert('Erreur', extractApiErrorMessage(err));
    }
  };

  const grouped = groupByDay(slots.data ?? []);

  return (
    <Modal visible={open} animationType="slide" presentationStyle="pageSheet" onRequestClose={handleClose}>
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
          <Pressable onPress={handleClose} hitSlop={8}>
            <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
              <X size={18} color="$slate700" />
            </YStack>
          </Pressable>
          <Paragraph fontSize={16} fontWeight="700" color="$slate900" flex={1} textAlign="center">
            Réserver une visite
          </Paragraph>
          <YStack width={36} />
        </XStack>

        {done ? (
          <YStack flex={1} alignItems="center" justifyContent="center" gap={14} padding="$5">
            <CheckCircle2 size={56} color={brand.success} />
            <Paragraph fontSize={20} fontWeight="700" textAlign="center">Réservation envoyée</Paragraph>
            <Paragraph fontSize={14} color="$slate500" textAlign="center" lineHeight={20}>
              Le bailleur confirmera votre visite sous peu. Vous la retrouverez dans "Mes réservations".
            </Paragraph>
            <Button backgroundColor="$brand" color="white" fontWeight="700" borderRadius={12} marginTop={6} onPress={handleClose}>
              OK
            </Button>
          </YStack>
        ) : slots.isLoading ? (
          <YStack flex={1} alignItems="center" justifyContent="center"><ActivityIndicator /></YStack>
        ) : slots.isError ? (
          <YStack padding="$5"><Paragraph color="$slate700">{extractApiErrorMessage(slots.error)}</Paragraph></YStack>
        ) : (slots.data ?? []).length === 0 ? (
          <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={10}>
            <Paragraph fontSize={15} fontWeight="700" color="$slate900" textAlign="center">
              Aucun créneau disponible
            </Paragraph>
            <Paragraph fontSize={13} color="$slate500" textAlign="center">
              Contactez directement le bailleur pour proposer une date.
            </Paragraph>
          </YStack>
        ) : (
          <ScrollView
            contentContainerStyle={{
              padding: 20,
              paddingBottom: insets.bottom + 24,
              gap: 18,
            }}
            showsVerticalScrollIndicator={false}
            keyboardShouldPersistTaps="handled"
          >
            <Paragraph fontSize={13} color="$slate500" lineHeight={20}>
              Choisissez un créneau qui vous convient. La visite sera confirmée par le bailleur.
            </Paragraph>

            {Object.entries(grouped).map(([day, daySlots]) => (
              <YStack key={day} gap={8}>
                <Paragraph fontSize={12} fontWeight="800" color="$slate500" textTransform="uppercase">
                  {day}
                </Paragraph>
                <XStack flexWrap="wrap" gap={8}>
                  {daySlots.map((s) => {
                    const active = selected != null && slotKey(selected) === slotKey(s);
                    const label = s.starts_at.replace(':', 'h');
                    return (
                      <Pressable key={slotKey(s)} onPress={() => setSelected(s)} hitSlop={4}>
                        <XStack
                          paddingHorizontal={14}
                          paddingVertical={9}
                          borderRadius={10}
                          borderWidth={1}
                          borderColor={active ? brand.primary : brand.slate300}
                          backgroundColor={active ? brand.primaryAlpha10 : '$background'}
                        >
                          <Paragraph fontSize={13.5} fontWeight={active ? '800' : '600'} color={active ? brand.primary : '$slate900'}>
                            {label}
                          </Paragraph>
                        </XStack>
                      </Pressable>
                    );
                  })}
                </XStack>
              </YStack>
            ))}

            <YStack gap={6}>
              <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">
                Note pour le bailleur (optionnel)
              </Paragraph>
              <TextInput
                value={notes}
                onChangeText={setNotes}
                placeholder="Précisez vos contraintes ou questions…"
                placeholderTextColor={brand.slate500}
                multiline
                style={{
                  borderWidth: 1,
                  borderColor: brand.slate300,
                  borderRadius: 12,
                  paddingHorizontal: 14,
                  paddingVertical: 10,
                  fontSize: 14,
                  color: brand.slate900,
                  minHeight: 80,
                  textAlignVertical: 'top',
                }}
              />
            </YStack>

            <Button
              size="$5"
              backgroundColor="$brand"
              color="white"
              fontWeight="700"
              borderRadius={14}
              disabled={!selected || reserve.isPending}
              onPress={handleConfirm}
            >
              {reserve.isPending ? 'Envoi…' : 'Confirmer la réservation'}
            </Button>
          </ScrollView>
        )}
      </YStack>
    </Modal>
  );
}

function groupByDay(slots: ViewingSlot[]): Record<string, ViewingSlot[]> {
  const out: Record<string, ViewingSlot[]> = {};
  for (const slot of slots) {
    let key = slot.date;
    try {
      // `slot.date` est `YYYY-MM-DD` → libellé lisible ; on parse à midi
      // pour éviter tout décalage de fuseau.
      const day = format(new Date(`${slot.date}T12:00:00`), 'EEEE d MMMM', { locale: fr });
      key = day.charAt(0).toUpperCase() + day.slice(1);
    } catch {
      /* garder la date brute en repli */
    }
    (out[key] ??= []).push(slot);
  }
  return out;
}
