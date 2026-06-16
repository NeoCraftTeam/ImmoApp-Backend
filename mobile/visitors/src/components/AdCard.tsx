import { Link } from 'expo-router';
import { Image } from 'expo-image';
import { Card, H4, Paragraph, XStack, YStack } from 'tamagui';

import { CompareButton } from '@/components/CompareButton';
import { FavoriteButton } from '@/components/FavoriteButton';
import { t } from '@/i18n';
import type { Ad } from '@/types/ad';

const CARD_HEIGHT = 220;

/**
 * Feed-card variant of an `Ad`. Designed to match the web AdCard
 * compact layout: 16:9 hero image, title + price overlay, quarter +
 * city below, a small badge row for boost / verified. Tapping
 * navigates to `/ads/[slug]` via expo-router's typed Link.
 *
 * Image strategy: `expo-image` (Cloudinary-style fade, on-disk cache,
 * priority `high` for the first three items). The thumbnail URL is
 * preferred over the full image to keep scroll bytes-per-card low.
 */
export function AdCard({ ad, priority = false }: { ad: Ad; priority?: boolean }) {
  const cover = ad.images.find((i) => i.is_primary) ?? ad.images[0];
  const coverUri = cover?.thumb ?? cover?.url;
  const locationLabel = [ad.quarter?.name, ad.quarter?.city_name].filter(Boolean).join(', ');
  const periodLabel = ad.price_period === 'jour' ? t('ad.perDay') : t('ad.perMonth');

  return (
    <Link
      href={{ pathname: '/ads/[slug]', params: { slug: ad.slug ?? ad.id } }}
      asChild
    >
      <Card
        elevate
        bordered
        marginVertical="$2"
        backgroundColor="$background"
        overflow="hidden"
        pressStyle={{ scale: 0.98 }}
        accessibilityRole="link"
        accessibilityLabel={`${ad.title}, ${locationLabel || 'lieu indisponible'}`}
      >
        <YStack height={CARD_HEIGHT} backgroundColor="$slate100">
          {coverUri ? (
            <Image
              source={{ uri: coverUri }}
              style={{ width: '100%', height: '100%' }}
              contentFit="cover"
              transition={200}
              priority={priority ? 'high' : 'normal'}
              accessibilityLabel={`Photo principale de ${ad.title}`}
            />
          ) : null}
          {/* Heart + compare buttons — overlaid top-right on the card
              image. The chip variants give them a translucent white
              background so the icons stay legible whatever the
              underlying photo. */}
          <XStack position="absolute" top={8} right={8} zIndex={2} gap="$1">
            <CompareButton ad={ad} size="small" />
            <FavoriteButton
              adId={ad.id}
              isFavorited={ad.is_favorited ?? false}
              size="small"
            />
          </XStack>
        </YStack>

        <YStack padding="$3" gap="$1">
          <XStack justifyContent="space-between" alignItems="flex-start" gap="$2">
            <H4 flex={1} numberOfLines={1}>
              {ad.title}
            </H4>
            {ad.keyscore != null && (
              <YStack
                backgroundColor="$brandAlpha10"
                paddingHorizontal="$2"
                paddingVertical="$1"
                borderRadius={999}
              >
                <Paragraph size="$2" color="$brand" fontWeight="700">
                  {ad.keyscore}
                </Paragraph>
              </YStack>
            )}
          </XStack>

          {locationLabel && (
            <Paragraph size="$3" color="$slate500" numberOfLines={1}>
              {locationLabel}
            </Paragraph>
          )}

          <XStack justifyContent="space-between" alignItems="center" marginTop="$2">
            <Paragraph fontWeight="800" size="$6" color="$brand">
              {ad.price != null
                ? `${ad.price.toLocaleString('fr-FR')} FCFA`
                : '—'}
              {ad.price != null && ad.transaction_type === 'location' && (
                <Paragraph color="$slate500" size="$3" fontWeight="500">
                  {' '}
                  {periodLabel}
                </Paragraph>
              )}
            </Paragraph>
            {ad.is_boosted && (
              <YStack
                backgroundColor="$warning"
                paddingHorizontal="$2"
                paddingVertical="$1"
                borderRadius={4}
              >
                <Paragraph size="$1" color="white" fontWeight="700">
                  BOOSTÉ
                </Paragraph>
              </YStack>
            )}
          </XStack>
        </YStack>
      </Card>
    </Link>
  );
}
