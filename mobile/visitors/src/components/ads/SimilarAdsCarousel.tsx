import { Link } from 'expo-router';
import { Image } from 'expo-image';
import { ActivityIndicator, FlatList, Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { useCurrency } from '@/hooks/useCurrency';
import { useSimilarAds } from '@/hooks/useSimilarAds';
import { t } from '@/i18n';
import type { Ad } from '@/types/ad';

interface Props {
  adId: string;
}

const CARD_WIDTH = 200;
const CARD_HEIGHT = 130;

/**
 * Horizontal carousel of similar ads. Inspired by the web
 * `SimilarAds` component but laid out as a paged-ish snap scroll
 * (decelerationRate "fast" gives the iOS Maps / Airbnb feel without
 * forcing one-card-per-page).
 */
export function SimilarAdsCarousel({ adId }: Props) {
  const { data, isLoading, isError } = useSimilarAds(adId);

  if (isLoading) {
    return (
      <YStack alignItems="center" paddingVertical={16}>
        <ActivityIndicator />
      </YStack>
    );
  }

  if (isError || !data || data.length === 0) {
    return null;
  }

  return (
    <YStack gap={12}>
      <Paragraph fontSize={17} fontWeight="700" color="$slate900">
        Annonces similaires
      </Paragraph>
      <FlatList
        data={data}
        keyExtractor={(item) => item.id}
        horizontal
        showsHorizontalScrollIndicator={false}
        snapToInterval={CARD_WIDTH + 12}
        decelerationRate="fast"
        contentContainerStyle={{ gap: 12, paddingRight: 20 }}
        renderItem={({ item }) => <SimilarCard ad={item} />}
      />
    </YStack>
  );
}

function SimilarCard({ ad }: { ad: Ad }) {
  const { format } = useCurrency();
  const cover = ad.images.find((i) => i.is_primary) ?? ad.images[0];
  const coverUri = cover?.thumb ?? cover?.url;
  const locationLabel = [ad.quarter?.name, ad.quarter?.city_name]
    .filter(Boolean)
    .join(', ');
  const periodLabel =
    ad.price_period === 'jour' ? t('ad.perDay') : t('ad.perMonth');

  return (
    <Link
      href={{ pathname: '/ads/[slug]', params: { slug: ad.id } }}
      asChild
    >
      <Pressable accessibilityRole="link" accessibilityLabel={ad.title}>
        <YStack width={CARD_WIDTH} gap={8}>
          <YStack
            width={CARD_WIDTH}
            height={CARD_HEIGHT}
            borderRadius={12}
            overflow="hidden"
            backgroundColor="$slate100"
          >
            {coverUri ? (
              <Image
                source={{ uri: coverUri }}
                style={{ width: '100%', height: '100%' }}
                contentFit="cover"
                transition={200}
              />
            ) : null}
          </YStack>
          <YStack gap={2}>
            <Paragraph
              fontSize={13}
              fontWeight="700"
              color="$slate900"
              numberOfLines={1}
            >
              {ad.title}
            </Paragraph>
            {locationLabel.length > 0 && (
              <Paragraph
                fontSize={11.5}
                color="$slate500"
                numberOfLines={1}
              >
                {locationLabel}
              </Paragraph>
            )}
            <XStack alignItems="baseline" gap={3} marginTop={2}>
              <Paragraph fontSize={13} fontWeight="800" color="$slate900" numberOfLines={1}>
                {ad.price != null ? format(ad.price) : '—'}
              </Paragraph>
              {ad.price != null && ad.transaction_type === 'location' && (
                <Paragraph fontSize={11} color="$slate500">
                  {periodLabel}
                </Paragraph>
              )}
            </XStack>
          </YStack>
        </YStack>
      </Pressable>
    </Link>
  );
}
