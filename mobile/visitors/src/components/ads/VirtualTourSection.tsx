import { Lock, Orbit } from '@tamagui/lucide-icons';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { TourViewer } from '@/components/ads/TourViewer';
import { brand } from '@/theme/tokens';
import type { Ad } from '@/types/ad';

const WEB_BASE_URL = 'https://keyhome.app';

interface Props {
  ad: Ad;
}

/**
 * Section « Visite 3D » — teaser cadenassé tant que l'annonce est
 * verrouillée. Déverrouillée : ouvre le viewer 360° NATIF in-app
 * (TourViewer / WebView + Photo Sphere Viewer) quand `tour_config` est
 * fourni ; repli sur l'ouverture in-app du site sinon.
 */
export function VirtualTourSection({ ad }: Props) {
  const [viewerOpen, setViewerOpen] = useState(false);

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

  const hasNativeTour = Boolean(ad.tour_config?.scenes?.some((s) => s.image_url));

  const handlePress = () => {
    if (hasNativeTour) {
      setViewerOpen(true);
    } else {
      // Repli : pas de config exploitable côté API → ouverture in-app du site.
      void WebBrowser.openBrowserAsync(
        `${WEB_BASE_URL}/ads/${encodeURIComponent(ad.slug ?? ad.id)}`,
      );
    }
  };

  return (
    <>
      <Pressable onPress={handlePress} hitSlop={4} accessibilityRole="button" accessibilityLabel="Visiter en 3D">
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
              {scenesLabel ? `${scenesLabel} · visite immersive 360°` : 'Visite immersive 360°'}
            </Paragraph>
          </YStack>
        </XStack>
      </Pressable>

      {hasNativeTour && ad.tour_config ? (
        <TourViewer
          visible={viewerOpen}
          tourConfig={ad.tour_config}
          onClose={() => setViewerOpen(false)}
        />
      ) : null}
    </>
  );
}
