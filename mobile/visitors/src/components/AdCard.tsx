import { Bed, Maximize, ShowerHead, Star } from '@tamagui/lucide-icons';
import { Link } from 'expo-router';
import { Image } from 'expo-image';
import { memo, useCallback, useRef } from 'react';
import { Animated, Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { CompareButton } from '@/components/CompareButton';
import { FavoriteButton } from '@/components/FavoriteButton';
import { useThemeColors } from '@/theme/useThemeColors';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';
import type { Ad } from '@/types/ad';

/**
 * Mobile AdCard — Airbnb-flat aesthetic mirrored from the web component.
 * The card has no border/shadow at rest; visual hierarchy comes from the
 * rounded 3:2 hero image and the typography stack below it.
 *
 * Press animation uses React Native's built-in `Animated` with
 * `useNativeDriver: true`, so the spring runs on the UI thread without
 * the Reanimated worklets bridge (which is fragile on Expo Go).
 *
 * Unlike the web variant we do NOT embed a per-card image carousel —
 * inside a 2-column grid each card is ~180 px wide, so swiping a tiny
 * paged FlatList competes with the outer vertical scroll. The full
 * gallery lives on the detail page; cards expose a photo count badge.
 */

const HERO_RADIUS = 14;

/** KeyScore — compact text-only badge. */
function KeyScoreBadge({ score }: { score: number }) {
  const colors = useThemeColors();
  const color =
    score >= 75 ? brand.success : score >= 50 ? brand.warning : brand.danger;
  return (
    <XStack
      alignItems="center"
      gap={3}
      paddingHorizontal={6}
      paddingVertical={2}
      borderRadius={999}
      backgroundColor={colors.faintFill}
    >
      <YStack width={6} height={6} borderRadius={3} backgroundColor={color} />
      <Paragraph fontSize={11} fontWeight="700" color={color} lineHeight={14}>
        {score}
      </Paragraph>
    </XStack>
  );
}

interface FeatureChipProps {
  icon: React.ReactNode;
  label: string;
}

function FeatureChip({ icon, label }: FeatureChipProps) {
  return (
    <XStack alignItems="center" gap={3}>
      {icon}
      <Paragraph fontSize={12} color="$slate500" lineHeight={14}>
        {label}
      </Paragraph>
    </XStack>
  );
}

interface AdCardProps {
  ad: Ad;
  priority?: boolean;
}

function AdCardComponent({ ad, priority = false }: AdCardProps) {
  const colors = useThemeColors();
  const scale = useRef(new Animated.Value(1)).current;

  const handlePressIn = useCallback(() => {
    Animated.spring(scale, {
      toValue: 0.965,
      useNativeDriver: true,
      speed: 50,
      bounciness: 0,
    }).start();
  }, [scale]);

  const handlePressOut = useCallback(() => {
    Animated.spring(scale, {
      toValue: 1,
      useNativeDriver: true,
      speed: 40,
      bounciness: 4,
    }).start();
  }, [scale]);

  const cover = ad.images.find((i) => i.is_primary) ?? ad.images[0];
  const coverUri = cover?.thumb ?? cover?.url;
  const locationLabel = [ad.quarter?.name, ad.quarter?.city_name]
    .filter(Boolean)
    .join(', ');
  const periodLabel =
    ad.price_period === 'jour' ? t('ad.perDay') : t('ad.perMonth');

  const statusLabel =
    ad.status === 'sold'
      ? 'Vendu'
      : ad.status === 'reserved'
        ? 'Réservé'
        : ad.status === 'rent'
          ? 'En location'
          : null;
  const statusBg =
    ad.status === 'sold'
      ? '#0A0A0F'
      : ad.status === 'reserved'
        ? brand.warning
        : ad.status === 'rent'
          ? brand.info
          : brand.primary;

  return (
    <Link
      href={{ pathname: '/ads/[slug]', params: { slug: ad.slug ?? ad.id } }}
      asChild
    >
      <Pressable
        onPressIn={handlePressIn}
        onPressOut={handlePressOut}
        accessibilityRole="link"
        accessibilityLabel={`${ad.title}, ${locationLabel || 'lieu indisponible'}`}
      >
        <Animated.View style={{ transform: [{ scale }] }}>
          <YStack gap={10}>
            {/* ── Hero image ──────────────────────────────────────────── */}
            <YStack
              width="100%"
              aspectRatio={3 / 2}
              borderRadius={HERO_RADIUS}
              overflow="hidden"
              backgroundColor={colors.track}
            >
              {coverUri ? (
                <Image
                  source={{ uri: coverUri }}
                  style={{ width: '100%', height: '100%' }}
                  contentFit="cover"
                  transition={220}
                  priority={priority ? 'high' : 'normal'}
                  accessibilityLabel={`Photo de ${ad.title}`}
                />
              ) : null}

              {/* Boosted badge — top-left */}
              {ad.is_boosted && (
                <XStack
                  position="absolute"
                  top={10}
                  left={10}
                  paddingHorizontal={8}
                  paddingVertical={4}
                  borderRadius={6}
                  backgroundColor={brand.warning}
                >
                  <Paragraph
                    fontSize={10}
                    fontWeight="800"
                    color="white"
                    letterSpacing={0.4}
                  >
                    BOOSTÉ
                  </Paragraph>
                </XStack>
              )}

              {/* Status badge — bottom-left */}
              {statusLabel && (
                <XStack
                  position="absolute"
                  bottom={10}
                  left={10}
                  paddingHorizontal={8}
                  paddingVertical={4}
                  borderRadius={6}
                  backgroundColor={statusBg}
                >
                  <Paragraph fontSize={10} fontWeight="700" color="white">
                    {statusLabel}
                  </Paragraph>
                </XStack>
              )}

              {/* Heart bookmark — top-right */}
              <YStack position="absolute" top={8} right={8} zIndex={2}>
                <FavoriteButton
                  adId={ad.id}
                  isFavorited={ad.is_favorited ?? false}
                  size="small"
                />
              </YStack>

            </YStack>

            {/* ── Title row : title + rating · keyscore ──────────────── */}
            <YStack gap={2}>
              <XStack alignItems="flex-start" gap={6}>
                <Paragraph
                  flex={1}
                  fontSize={14}
                  fontWeight="700"
                  color="$slate900"
                  lineHeight={18}
                  numberOfLines={1}
                >
                  {ad.title}
                </Paragraph>
                <XStack alignItems="center" gap={6} flexShrink={0}>
                  {ad.rating != null && (
                    <XStack alignItems="center" gap={2}>
                      <Star size={12} color={brand.slate900} fill={brand.slate900} />
                      <Paragraph
                        fontSize={12}
                        fontWeight="600"
                        color="$slate900"
                        lineHeight={14}
                      >
                        {ad.rating.toFixed(1)}
                      </Paragraph>
                    </XStack>
                  )}
                  {ad.keyscore != null && <KeyScoreBadge score={ad.keyscore} />}
                </XStack>
              </XStack>

              {/* Location */}
              {locationLabel.length > 0 && (
                <Paragraph
                  fontSize={12.5}
                  color="$slate500"
                  numberOfLines={1}
                  lineHeight={16}
                >
                  {locationLabel}
                </Paragraph>
              )}

              {/* Features row */}
              {((ad.bedrooms ?? 0) > 0 ||
                (ad.bathrooms ?? 0) > 0 ||
                (ad.surface_area ?? 0) > 0) && (
                <XStack alignItems="center" gap={10} marginTop={2} flexWrap="wrap">
                  {(ad.bedrooms ?? 0) > 0 && (
                    <FeatureChip
                      icon={<Bed size={13} color={brand.slate500} />}
                      label={String(ad.bedrooms)}
                    />
                  )}
                  {(ad.bathrooms ?? 0) > 0 && (
                    <FeatureChip
                      icon={<ShowerHead size={13} color={brand.slate500} />}
                      label={String(ad.bathrooms)}
                    />
                  )}
                  {(ad.surface_area ?? 0) > 0 && (
                    <FeatureChip
                      icon={<Maximize size={13} color={brand.slate500} />}
                      label={`${ad.surface_area} m²`}
                    />
                  )}
                </XStack>
              )}

              {/* Price + Compare */}
              <XStack
                alignItems="center"
                justifyContent="space-between"
                marginTop={4}
                gap={8}
              >
                <XStack alignItems="baseline" gap={3} flex={1}>
                  <Paragraph
                    fontSize={15}
                    fontWeight="800"
                    color="$slate900"
                    numberOfLines={1}
                  >
                    {ad.price != null
                      ? `${ad.price.toLocaleString('fr-FR')} FCFA`
                      : '—'}
                  </Paragraph>
                  {ad.price != null && ad.transaction_type === 'location' && (
                    <Paragraph fontSize={12} color="$slate500" fontWeight="500">
                      {periodLabel}
                    </Paragraph>
                  )}
                </XStack>
                <CompareButton ad={ad} size="small" />
              </XStack>
            </YStack>
          </YStack>
        </Animated.View>
      </Pressable>
    </Link>
  );
}

export const AdCard = memo(AdCardComponent, (prev, next) => {
  return (
    prev.ad.id === next.ad.id &&
    prev.ad.is_favorited === next.ad.is_favorited &&
    prev.priority === next.priority
  );
});
