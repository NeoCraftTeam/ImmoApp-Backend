import { Check } from '@tamagui/lucide-icons';
import { FlatList, Modal, Pressable } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useCurrency } from '@/hooks/useCurrency';
import { symbolFor } from '@/services/currency';
import { brand } from '@/theme/tokens';

/** Libellés lisibles par devise (les plus courantes d'abord côté store). */
const CURRENCY_LABELS: Record<string, string> = {
  XAF: 'Franc CFA (Afrique centrale)',
  XOF: 'Franc CFA (Afrique de l’Ouest)',
  EUR: 'Euro',
  USD: 'Dollar américain',
  GBP: 'Livre sterling',
  CHF: 'Franc suisse',
  CAD: 'Dollar canadien',
  NGN: 'Naira nigérian',
  GHS: 'Cedi ghanéen',
  KES: 'Shilling kényan',
  ZAR: 'Rand sud-africain',
  AED: 'Dirham émirati',
  CNY: 'Yuan chinois',
  JPY: 'Yen japonais',
  INR: 'Roupie indienne',
  MAD: 'Dirham marocain',
};

/**
 * Sélecteur de devise d'affichage. Les prix restent en FCFA côté serveur —
 * seul l'affichage change. Le choix est persisté et prime sur la détection
 * automatique par IP.
 */
export function CurrencySelectorModal({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const insets = useSafeAreaInsets();
  const { currency, supported, setCurrency } = useCurrency();

  return (
    <Modal visible={open} transparent animationType="slide" onRequestClose={onClose}>
      <YStack flex={1} justifyContent="flex-end" backgroundColor="rgba(0,0,0,0.45)">
        <Pressable style={{ flex: 1 }} onPress={onClose} />
        <YStack
          backgroundColor="$background"
          borderTopLeftRadius={24}
          borderTopRightRadius={24}
          paddingTop={16}
          paddingBottom={insets.bottom + 16}
          maxHeight="80%"
        >
          <YStack paddingHorizontal={20} paddingBottom={8} gap={4}>
            <H2 fontSize={18} fontWeight="800" color="$slate900">
              Devise d’affichage
            </H2>
            <Paragraph fontSize={12.5} color="$slate500">
              Les prix restent payés en FCFA — seul l’affichage change.
            </Paragraph>
          </YStack>

          <FlatList
            data={supported}
            keyExtractor={(item) => item}
            contentContainerStyle={{ paddingHorizontal: 12, paddingBottom: 8 }}
            renderItem={({ item }) => {
              const active = item === currency;
              return (
                <Pressable
                  onPress={() => {
                    setCurrency(item);
                    onClose();
                  }}
                  accessibilityRole="button"
                >
                  <XStack
                    alignItems="center"
                    gap={12}
                    paddingHorizontal={12}
                    paddingVertical={13}
                    borderRadius={12}
                    backgroundColor={active ? '$brandAlpha10' : 'transparent'}
                  >
                    <YStack
                      width={44}
                      height={32}
                      borderRadius={8}
                      alignItems="center"
                      justifyContent="center"
                      backgroundColor="$slate100"
                    >
                      <Paragraph fontSize={13} fontWeight="800" color="$slate900">
                        {symbolFor(item)}
                      </Paragraph>
                    </YStack>
                    <YStack flex={1}>
                      <Paragraph fontSize={15} fontWeight="700" color="$slate900">
                        {item}
                      </Paragraph>
                      <Paragraph fontSize={12} color="$slate500" numberOfLines={1}>
                        {CURRENCY_LABELS[item] ?? item}
                      </Paragraph>
                    </YStack>
                    {active ? <Check size={20} color={brand.primary} /> : null}
                  </XStack>
                </Pressable>
              );
            }}
          />
        </YStack>
      </YStack>
    </Modal>
  );
}
