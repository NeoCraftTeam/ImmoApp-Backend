import { ChevronRight, MapPin, Phone, UserCircle } from '@tamagui/lucide-icons';
import { Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';
import type { AuthUser } from '@/types/user';

interface Step {
  key: string;
  label: string;
  done: boolean;
  icon: React.ReactNode;
}

interface Props {
  user: AuthUser;
  /** Ouvre l'éditeur de profil. */
  onPress: () => void;
}

/**
 * Bannière « Complétez votre profil » — réplique la section web : barre
 * de progression + puces des étapes restantes (photo, téléphone, ville)
 * + CTA. Ne s'affiche que si au moins une étape manque (le parent gère
 * aussi la condition `isAuthenticated`).
 */
export function ClientProfileBanner({ user, onPress }: Props) {
  const steps: Step[] = [
    {
      key: 'avatar',
      label: 'Photo',
      done: Boolean(user.avatar),
      icon: <UserCircle size={13} color={brand.primary} />,
    },
    {
      key: 'phone',
      label: 'Téléphone',
      done: Boolean(user.phone_number),
      icon: <Phone size={13} color={brand.primary} />,
    },
    {
      key: 'city',
      label: 'Ville',
      done: Boolean(user.city_id),
      icon: <MapPin size={13} color={brand.primary} />,
    },
  ];

  const doneCount = steps.filter((s) => s.done).length;
  if (doneCount === steps.length) {
    return null;
  }
  const progress = doneCount / steps.length;

  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel="Compléter mon profil">
      <YStack
        gap={10}
        padding={14}
        borderRadius={16}
        borderWidth={1}
        borderColor={brand.primaryAlpha20}
        backgroundColor={brand.primaryAlpha10}
        marginBottom={16}
      >
        <XStack alignItems="center" gap={8}>
          <YStack flex={1} gap={2}>
            <Paragraph fontSize={14.5} fontWeight="800" color="$slate900">
              Complétez votre profil
            </Paragraph>
            <Paragraph fontSize={12} color="$slate500">
              {doneCount}/{steps.length} · gagnez en visibilité auprès des bailleurs
            </Paragraph>
          </YStack>
          <ChevronRight size={18} color={brand.primary} />
        </XStack>

        {/* Barre de progression */}
        <YStack height={6} borderRadius={3} backgroundColor="$slate100" overflow="hidden">
          <YStack height="100%" width={`${progress * 100}%`} backgroundColor={brand.primary} />
        </YStack>

        {/* Puces des étapes restantes */}
        <XStack gap={8} flexWrap="wrap">
          {steps
            .filter((s) => !s.done)
            .map((s) => (
              <XStack
                key={s.key}
                alignItems="center"
                gap={5}
                paddingHorizontal={10}
                paddingVertical={5}
                borderRadius={999}
                backgroundColor="$background"
                borderWidth={1}
                borderColor={brand.primaryAlpha20}
              >
                {s.icon}
                <Paragraph fontSize={12} fontWeight="700" color={brand.primary}>
                  {s.label}
                </Paragraph>
              </XStack>
            ))}
        </XStack>
      </YStack>
    </Pressable>
  );
}
