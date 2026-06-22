import { Smartphone, CreditCard, Wallet } from '@tamagui/lucide-icons';
import type { ReactNode } from 'react';
import { Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';
import type { PaymentMethod } from '@/types/payment';

const CHANNEL_ICONS: Record<string, ReactNode> = {
  mobile_money: <Smartphone size={20} color={brand.primary} />,
  card: <CreditCard size={20} color={brand.primary} />,
  wallet: <Wallet size={20} color={brand.primary} />,
};

interface Props {
  methods: PaymentMethod[];
  selected?: string;
  onSelect: (method: PaymentMethod) => void;
}

/**
 * Picker simple — chaque méthode est une carte radio-like. Pas de
 * Tamagui Select pour rester full-touch sur mobile et lisible. La
 * sélection est purement visuelle, le composant ne sait rien du
 * payload ni du gateway.
 */
export function PaymentMethodPicker({ methods, selected, onSelect }: Props) {
  if (methods.length === 0) {
    return (
      <YStack padding={12} borderRadius={10} backgroundColor="$slate100">
        <Paragraph fontSize={12.5} color="$slate500">
          Aucune méthode de paiement disponible pour le moment.
        </Paragraph>
      </YStack>
    );
  }

  return (
    <YStack gap={8}>
      {methods.map((m) => {
        const isSel = selected === m.code;
        const icon = m.channel ? CHANNEL_ICONS[m.channel] : null;
        return (
          <Pressable
            key={m.id}
            onPress={() => onSelect(m)}
            accessibilityRole="radio"
            accessibilityState={{ selected: isSel }}
            accessibilityLabel={m.label}
          >
            <XStack
              padding={12}
              gap={12}
              borderRadius={12}
              borderWidth={isSel ? 2 : 1}
              borderColor={isSel ? brand.primary : '$slate300'}
              backgroundColor={isSel ? brand.primaryAlpha10 : '$background'}
              alignItems="center"
            >
              <YStack
                width={36}
                height={36}
                borderRadius={18}
                alignItems="center"
                justifyContent="center"
                backgroundColor={brand.primaryAlpha10}
              >
                {icon ?? <Smartphone size={18} color={brand.primary} />}
              </YStack>
              <YStack flex={1} gap={2}>
                <Paragraph fontSize={13.5} fontWeight="800" color="$slate900">
                  {m.label}
                </Paragraph>
                <Paragraph fontSize={11.5} color="$slate500">
                  {m.gateway === 'stripe' ? 'Cartes Visa, Mastercard' : 'Mobile money'}
                </Paragraph>
              </YStack>
              <YStack
                width={20}
                height={20}
                borderRadius={10}
                borderWidth={2}
                borderColor={isSel ? brand.primary : '$slate300'}
                alignItems="center"
                justifyContent="center"
              >
                {isSel ? (
                  <YStack width={10} height={10} borderRadius={5} backgroundColor={brand.primary} />
                ) : null}
              </YStack>
            </XStack>
          </Pressable>
        );
      })}
    </YStack>
  );
}
