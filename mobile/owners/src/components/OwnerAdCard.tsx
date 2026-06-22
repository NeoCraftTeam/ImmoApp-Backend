import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { Eye, Heart, ImageOff, Rocket } from '@tamagui/lucide-icons';
import { memo, useCallback, useRef } from 'react';
import { Animated, Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { StatusBadge } from '@/components/StatusBadge';
import { brand } from '@/theme/tokens';
import { formatFcfa, formatCompact } from '@/utils/format';
import type { Ad } from '@/types/ad';

/**
 * Management-oriented ad card for the owner ads list. Surfaces the
 * status, price, and engagement counters that matter when managing a
 * listing (vs. the visitor card which optimises for browsing). Tapping
 * navigates to the ad detail/management screen.
 */
function OwnerAdCardComponent({ ad }: { ad: Ad }) {
  const router = useRouter();
  const scale = useRef(new Animated.Value(1)).current;
  const cover = ad.images?.find((i) => i.is_primary)?.url ?? ad.images?.[0]?.url;

  const pressIn = useCallback(() => {
    Animated.spring(scale, { toValue: 0.97, useNativeDriver: true, speed: 50, bounciness: 6 }).start();
  }, [scale]);
  const pressOut = useCallback(() => {
    Animated.spring(scale, { toValue: 1, useNativeDriver: true, speed: 50, bounciness: 6 }).start();
  }, [scale]);

  return (
    <Pressable
      onPressIn={pressIn}
      onPressOut={pressOut}
      onPress={() => router.push(`/ads/${ad.id}` as never)}
    >
      <Animated.View style={{ transform: [{ scale }] }}>
        <XStack
          backgroundColor="$background"
          borderWidth={1}
          borderColor="$slate300"
          borderRadius={16}
          overflow="hidden"
          gap={0}
        >
          {/* Cover */}
          <YStack width={108} height={108} backgroundColor="$slate100">
            {cover ? (
              <Image
                source={{ uri: cover }}
                style={{ width: '100%', height: '100%' }}
                contentFit="cover"
                transition={150}
              />
            ) : (
              <YStack flex={1} alignItems="center" justifyContent="center">
                <ImageOff size={24} color={brand.slate300} />
              </YStack>
            )}
            {ad.is_boosted ? (
              <XStack
                position="absolute"
                top={6}
                left={6}
                backgroundColor={brand.accent}
                paddingHorizontal={6}
                paddingVertical={2}
                borderRadius={6}
                alignItems="center"
                gap={3}
              >
                <Rocket size={10} color="white" />
                <Paragraph fontSize={9} fontWeight="800" color="white">
                  BOOST
                </Paragraph>
              </XStack>
            ) : null}
          </YStack>

          {/* Body */}
          <YStack flex={1} padding={12} gap={6} justifyContent="space-between">
            <YStack gap={5}>
              <XStack alignItems="center" justifyContent="space-between" gap={8}>
                <StatusBadge status={ad.status} size="sm" />
                {ad.is_visible === false ? (
                  <Paragraph fontSize={10} fontWeight="700" color="$slate500">
                    Masquée
                  </Paragraph>
                ) : null}
              </XStack>
              <Paragraph fontSize={14.5} fontWeight="700" color="$slate900" numberOfLines={1}>
                {ad.title || 'Sans titre'}
              </Paragraph>
              <Paragraph fontSize={12} color="$slate500" numberOfLines={1}>
                {ad.quarter?.name ?? ad.adresse ?? '—'}
              </Paragraph>
            </YStack>

            <XStack alignItems="center" justifyContent="space-between">
              <Paragraph fontSize={14} fontWeight="900" color="$brand">
                {formatFcfa(ad.price)}
                {ad.price_period ? (
                  <Paragraph fontSize={11} color="$slate500" fontWeight="600">
                    {' '}
                    /{ad.price_period}
                  </Paragraph>
                ) : null}
              </Paragraph>
              <XStack gap={12} alignItems="center">
                <XStack gap={3} alignItems="center">
                  <Eye size={13} color={brand.slate500} />
                  <Paragraph fontSize={11.5} color="$slate500" fontWeight="600">
                    {formatCompact(ad.view_count)}
                  </Paragraph>
                </XStack>
                <XStack gap={3} alignItems="center">
                  <Heart size={13} color={brand.slate500} />
                  <Paragraph fontSize={11.5} color="$slate500" fontWeight="600">
                    {formatCompact(ad.reviews_count)}
                  </Paragraph>
                </XStack>
              </XStack>
            </XStack>
          </YStack>
        </XStack>
      </Animated.View>
    </Pressable>
  );
}

export const OwnerAdCard = memo(OwnerAdCardComponent);
