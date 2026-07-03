import { Lock, Orbit } from '@tamagui/lucide-icons';
import { Linking, Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';
import type { Ad } from '@/types/ad';

const WEB_BASE_URL = 'https://keyhome.app';

interface Props {
  ad: Ad;
}

/**
 * Section « Visite 3D » — port mobile du bloc web : teaser cadenassé
 * tant que l'annonce est verrouillée, bouton « Visiter en 3D » sinon.
 * Le viewer photo-sphère du web (`TourViewerPSV`) n'a pas d'équivalent
 * RN sans nouvelle dépendance : le bouton ouvre la visite sur le site
 * web dans le navigateur.
 */
export function VirtualTourSection({ ad }: Props) {
  if (!ad.has_3d_tour) {
    return null;
  }

  const scenesLabel =
    ad.tour_scenes_count != null && ad.tour_scenes_count > 0
      ? `${ad.tour_scenes_count} pièce${ad.tour_scenes_count > 1 ? 's' : ''}`
      : null;

  if (!ad.is_unlocked) {
    return (
      <YStack
        padding={14}
        gap={8}
        borderRadius={14}
        borderWidth={1.5}
        borderColor="$slate300"
        borderStyle="dashed"
      >
        <XStack alignItems="center" gap={10}>
          <Orbit size={20} color={brand.primary} />
          <Paragraph fontSize={14.5} fontWeight="800" color="$slate900" flex={1}>
            Visite Virtuelle 3D disponible
          </Paragraph>
          <Lock size={16} color="$slate500" />
        </XStack>
        <Paragraph fontSize={13} color="$slate500" lineHeight={19}>
          {scenesLabel
            ? `${scenesLabel} à explorer — déverrouillez pour accéder à la visite 360°.`
            : 'Déverrouillez pour accéder à la visite 360°.'}
        </Paragraph>
      </YStack>
    );
  }

  return (
    <Pressable
      onPress={() =>
        void Linking.openURL(`${WEB_BASE_URL}/ads/${encodeURIComponent(ad.slug ?? ad.id)}`)
      }
      hitSlop={4}
      accessibilityRole="button"
      accessibilityLabel="Visiter en 3D"
    >
      <XStack
        alignItems="center"
        gap={10}
        paddingHorizontal={14}
        paddingVertical={12}
        borderRadius={14}
        backgroundColor={brand.primary}
      >
        <Orbit size={20} color="white" />
        <YStack flex={1} gap={1}>
          <Paragraph fontSize={14.5} fontWeight="800" color="white">
            Visiter en 3D
          </Paragraph>
          <Paragraph fontSize={12} color="rgba(255,255,255,0.85)">
            {scenesLabel
              ? `${scenesLabel} · visite immersive 360°`
              : 'Visite immersive 360°'}
          </Paragraph>
        </YStack>
      </XStack>
    </Pressable>
  );
}
