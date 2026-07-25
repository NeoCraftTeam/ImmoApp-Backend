import { CreditCard, Star, Trash2 } from '@tamagui/lucide-icons';
import { Alert, Pressable } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import {
  useDeleteStripeMethod,
  useSetDefaultStripeMethod,
  useStripeMethods,
} from '@/hooks/usePayments';
import { brand } from '@/theme/tokens';
import type { SavedCard } from '@/types/payment';

const BRAND_LABELS: Record<string, string> = {
  visa: 'Visa',
  mastercard: 'Mastercard',
  amex: 'American Express',
  discover: 'Discover',
};

function formatExpiry(month: number, year: number): string {
  const mm = String(month).padStart(2, '0');
  const yy = String(year).slice(-2);

  return `${mm}/${yy}`;
}

function CardRow({
  card,
  onDelete,
  onSetDefault,
  busy,
}: {
  card: SavedCard;
  onDelete: (id: string) => void;
  onSetDefault: (id: string) => void;
  busy: boolean;
}) {
  const label = BRAND_LABELS[card.brand.toLowerCase()] ?? card.brand;

  return (
    <XStack
      padding={14}
      gap={12}
      borderRadius={14}
      borderWidth={1}
      borderColor="$slate300"
      alignItems="center"
      backgroundColor="$background"
      opacity={busy ? 0.6 : 1}
    >
      <YStack
        width={44}
        height={44}
        borderRadius={22}
        backgroundColor={brand.primaryAlpha10}
        alignItems="center"
        justifyContent="center"
      >
        <CreditCard size={20} color={brand.primary} />
      </YStack>
      <YStack flex={1} gap={2}>
        <Paragraph fontSize={14} fontWeight="700" color="$slate900">
          {label} ·••• {card.last4}
        </Paragraph>
        <Paragraph fontSize={11.5} color="$slate500">
          Expire {formatExpiry(card.exp_month, card.exp_year)}
          {card.is_default ? ' · Par défaut' : ''}
        </Paragraph>
      </YStack>
      <XStack gap={4}>
        {!card.is_default ? (
          <Pressable
            onPress={() => onSetDefault(card.id)}
            disabled={busy}
            accessibilityLabel="Définir comme carte par défaut"
            hitSlop={8}
          >
            <YStack padding={8}>
              <Star size={18} color={brand.slate500} />
            </YStack>
          </Pressable>
        ) : null}
        <Pressable
          onPress={() => onDelete(card.id)}
          disabled={busy}
          accessibilityLabel="Supprimer la carte"
          hitSlop={8}
        >
          <YStack padding={8}>
            <Trash2 size={18} color={brand.danger} />
          </YStack>
        </Pressable>
      </XStack>
    </XStack>
  );
}

/**
 * Liste les cartes Stripe enregistrées (suppression / défaut).
 * L'ajout d'une nouvelle carte reste sur le web (SetupIntent + Elements).
 */
export function SavedCardsSection({ enabled = true }: { enabled?: boolean }) {
  const { data: cards = [], isLoading, isError, refetch, isRefetching } =
    useStripeMethods(enabled);
  const deleteMethod = useDeleteStripeMethod();
  const setDefault = useSetDefaultStripeMethod();
  const busy = deleteMethod.isPending || setDefault.isPending;

  const handleDelete = (pmId: string) => {
    Alert.alert(
      'Supprimer cette carte ?',
      'Elle ne pourra plus être utilisée pour vos prochains paiements.',
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Supprimer',
          style: 'destructive',
          onPress: () => {
            deleteMethod.mutate(pmId, {
              onError: (err) => {
                Alert.alert('Erreur', err.message);
              },
            });
          },
        },
      ],
    );
  };

  const handleSetDefault = (pmId: string) => {
    setDefault.mutate(pmId, {
      onError: (err) => {
        Alert.alert('Erreur', err.message);
      },
    });
  };

  return (
    <YStack gap={10}>
      <YStack gap={4}>
        <Paragraph fontSize={16} fontWeight="900" color="$slate900">
          Cartes enregistrées
        </Paragraph>
        <Paragraph fontSize={12} color="$slate500">
          Gérez vos cartes Stripe. Pour en ajouter une nouvelle, utilisez keyhome.app
          depuis un navigateur.
        </Paragraph>
      </YStack>

      {isLoading ? (
        <YStack height={80} alignItems="center" justifyContent="center">
          <Spinner color={brand.primary} />
        </YStack>
      ) : isError ? (
        <Pressable onPress={() => refetch()} accessibilityRole="button">
          <YStack
            padding={12}
            borderRadius={12}
            backgroundColor={brand.primaryAlpha10}
          >
            <Paragraph fontSize={12.5} color={brand.primaryHover}>
              Impossible de charger vos cartes. Toucher pour réessayer.
            </Paragraph>
          </YStack>
        </Pressable>
      ) : cards.length === 0 ? (
        <YStack
          padding={14}
          borderRadius={12}
          borderWidth={1}
          borderColor="$slate300"
          backgroundColor="$background"
        >
          <Paragraph fontSize={12.5} color="$slate500">
            Aucune carte enregistrée pour le moment.
          </Paragraph>
        </YStack>
      ) : (
        <YStack gap={8} opacity={isRefetching ? 0.85 : 1}>
          {cards.map((card) => (
            <CardRow
              key={card.id}
              card={card}
              onDelete={handleDelete}
              onSetDefault={handleSetDefault}
              busy={busy}
            />
          ))}
        </YStack>
      )}
    </YStack>
  );
}
