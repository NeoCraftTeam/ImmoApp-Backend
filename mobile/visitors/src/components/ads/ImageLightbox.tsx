import { X } from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import { useCallback, useRef, useState } from 'react';
import {
  FlatList,
  Modal,
  Pressable,
  useWindowDimensions,
  type NativeScrollEvent,
  type NativeSyntheticEvent,
} from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import type { AdImage } from '@/types/ad';

interface Props {
  images: AdImage[];
  /** Index de départ (image tapée dans le carrousel héro). */
  initialIndex: number;
  visible: boolean;
  onClose: () => void;
}

/**
 * Galerie plein écran swipeable — réplique l'ImageLightbox web. Modal
 * noir, une image par page (swipe horizontal), compteur + bouton fermer
 * dans une barre haute. Utilise la variante `large` de chaque image.
 */
export function ImageLightbox({ images, initialIndex, visible, onClose }: Props) {
  const { width, height } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const [index, setIndex] = useState(initialIndex);
  const listRef = useRef<FlatList<AdImage> | null>(null);

  const onMomentumEnd = useCallback(
    (e: NativeSyntheticEvent<NativeScrollEvent>) => {
      const next = Math.round(e.nativeEvent.contentOffset.x / width);
      setIndex(next);
    },
    [width],
  );

  return (
    <Modal
      visible={visible}
      transparent={false}
      animationType="fade"
      onRequestClose={onClose}
      statusBarTranslucent
    >
      <YStack flex={1} backgroundColor="#000000">
        <FlatList
          ref={listRef}
          data={images}
          keyExtractor={(item) => `lightbox-${item.id}`}
          horizontal
          pagingEnabled
          showsHorizontalScrollIndicator={false}
          initialScrollIndex={initialIndex}
          getItemLayout={(_, i) => ({ length: width, offset: width * i, index: i })}
          onMomentumScrollEnd={onMomentumEnd}
          renderItem={({ item }) => (
            <YStack width={width} height={height} alignItems="center" justifyContent="center">
              <Image
                source={{ uri: item.large ?? item.url }}
                style={{ width, height: height * 0.8 }}
                contentFit="contain"
                transition={150}
                accessibilityLabel="Photo de l'annonce"
              />
            </YStack>
          )}
        />

        {/* Barre haute : fermer + compteur */}
        <XStack
          position="absolute"
          top={insets.top + 8}
          left={0}
          right={0}
          paddingHorizontal={16}
          alignItems="center"
          justifyContent="space-between"
        >
          <Pressable
            onPress={onClose}
            hitSlop={10}
            accessibilityRole="button"
            accessibilityLabel="Fermer la galerie"
          >
            <YStack
              width={40}
              height={40}
              borderRadius={20}
              backgroundColor="rgba(0,0,0,0.5)"
              alignItems="center"
              justifyContent="center"
            >
              <X size={22} color="white" />
            </YStack>
          </Pressable>
          {images.length > 1 && (
            <YStack
              paddingHorizontal={12}
              paddingVertical={6}
              borderRadius={999}
              backgroundColor="rgba(0,0,0,0.5)"
            >
              <Paragraph fontSize={13} fontWeight="700" color="white">
                {index + 1} / {images.length}
              </Paragraph>
            </YStack>
          )}
        </XStack>
      </YStack>
    </Modal>
  );
}
