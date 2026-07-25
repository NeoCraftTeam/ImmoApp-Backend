import { ChevronLeft, ChevronRight, X } from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  FlatList,
  Modal,
  Pressable,
  ScrollView,
  useWindowDimensions,
  type NativeScrollEvent,
  type NativeSyntheticEvent,
} from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useMotionPresets } from '@/hooks/useMotionPresets';
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
 * noir, une image par page (swipe horizontal + chevrons), compteur et
 * bouton fermer dans une barre haute, pincement/zoom par image (iOS —
 * la ScrollView RN n'expose le zoom que là ; Android garde le swipe).
 * L'index se resynchronise à chaque ouverture.
 */
export function ImageLightbox({ images, initialIndex, visible, onClose }: Props) {
  const { width, height } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const { scrollAnimated, reducedMotion } = useMotionPresets();
  const [index, setIndex] = useState(initialIndex);
  const listRef = useRef<FlatList<AdImage> | null>(null);

  // Ré-ouvrir sur une autre photo doit repartir du bon index — le
  // useState initial ne suffit pas (le composant reste monté).
  useEffect(() => {
    if (!visible) return;
    setIndex(initialIndex);
    requestAnimationFrame(() => {
      listRef.current?.scrollToIndex({ index: initialIndex, animated: false });
    });
  }, [visible, initialIndex]);

  const onMomentumEnd = useCallback(
    (e: NativeSyntheticEvent<NativeScrollEvent>) => {
      const next = Math.round(e.nativeEvent.contentOffset.x / width);
      setIndex(Math.max(0, Math.min(images.length - 1, next)));
    },
    [width, images.length],
  );

  const goTo = (next: number) => {
    const clamped = Math.max(0, Math.min(images.length - 1, next));
    listRef.current?.scrollToIndex({ index: clamped, animated: scrollAnimated });
    setIndex(clamped);
  };

  return (
    <Modal
      visible={visible}
      transparent={false}
      animationType={reducedMotion ? 'none' : 'fade'}
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
            <ScrollView
              style={{ width, height }}
              maximumZoomScale={3}
              minimumZoomScale={1}
              bouncesZoom
              showsVerticalScrollIndicator={false}
              showsHorizontalScrollIndicator={false}
              centerContent
              contentContainerStyle={{
                width,
                height,
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <Image
                source={{ uri: item.large ?? item.url }}
                style={{ width, height: height * 0.8 }}
                contentFit="contain"
                transition={150}
                accessibilityLabel="Photo de l'annonce"
              />
            </ScrollView>
          )}
        />

        {/* Chevrons de navigation (en plus du swipe) */}
        {index > 0 && (
          <Pressable
            onPress={() => goTo(index - 1)}
            hitSlop={10}
            accessibilityRole="button"
            accessibilityLabel="Photo précédente"
            style={{ position: 'absolute', left: 10, top: height / 2 - 22 }}
          >
            <YStack width={44} height={44} borderRadius={22} backgroundColor="rgba(0,0,0,0.45)" alignItems="center" justifyContent="center">
              <ChevronLeft size={26} color="white" />
            </YStack>
          </Pressable>
        )}
        {index < images.length - 1 && (
          <Pressable
            onPress={() => goTo(index + 1)}
            hitSlop={10}
            accessibilityRole="button"
            accessibilityLabel="Photo suivante"
            style={{ position: 'absolute', right: 10, top: height / 2 - 22 }}
          >
            <YStack width={44} height={44} borderRadius={22} backgroundColor="rgba(0,0,0,0.45)" alignItems="center" justifyContent="center">
              <ChevronRight size={26} color="white" />
            </YStack>
          </Pressable>
        )}

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
