import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { Image } from 'expo-image';
import { useState } from 'react';
import { ActivityIndicator, FlatList, useWindowDimensions } from 'react-native';
import { Button, H2, H4, Paragraph, ScrollView, Separator, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useAd } from '@/hooks/useAd';
import { useSession } from '@/auth/SessionProvider';
import { t } from '@/i18n';

/**
 * Ad-detail screen — the conversion surface. Layout follows the web
 * `AdDetailClient`: image carousel header, title + price, key facts
 * row (bedrooms / surface / parking / keyscore), description, location
 * teaser, and the primary CTA "Contacter". Contact prompts sign-in
 * when the visitor is anonymous, matching the web's gating.
 *
 * Map + neighborhood scorecard + directions panel are deliberately
 * out of scope for the v1 — they require @rnmapbox/maps which adds a
 * ~20 MB native dependency and warrants its own setup PR.
 */
export default function AdDetail() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { isAuthenticated } = useSession();
  const { width } = useWindowDimensions();

  const { data: ad, isLoading, isError, error } = useAd(slug);
  const [activeImage, setActiveImage] = useState(0);

  if (isLoading) {
    return (
      <YStack flex={1} backgroundColor="$background" justifyContent="center" alignItems="center">
        <ActivityIndicator />
      </YStack>
    );
  }

  if (isError || !ad) {
    return (
      <YStack
        flex={1}
        backgroundColor="$background"
        justifyContent="center"
        alignItems="center"
        padding="$5"
        gap="$3"
      >
        <Paragraph color="$slate700" textAlign="center">
          {extractApiErrorMessage(error)}
        </Paragraph>
        <Button onPress={() => router.back()} size="$3">
          {t('common.back')}
        </Button>
      </YStack>
    );
  }

  const locationLabel = [ad.quarter?.name, ad.quarter?.city_name].filter(Boolean).join(', ');
  const periodLabel = ad.price_period === 'jour' ? t('ad.perDay') : t('ad.perMonth');

  const handleContact = () => {
    if (!isAuthenticated) {
      router.push('/(auth)/login');
      return;
    }
    // Real contact flow lands in a follow-up — for v1 we surface the
    // intent so testers know the button is wired up to navigation.
    router.push('/(auth)/login');
  };

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$background">
        <ScrollView
          contentContainerStyle={{ paddingBottom: insets.bottom + 96 }}
          showsVerticalScrollIndicator={false}
        >
          {/* Hero carousel */}
          <YStack height={width * 0.75} backgroundColor="$slate100">
            <FlatList
              data={ad.images}
              keyExtractor={(img) => String(img.id)}
              horizontal
              pagingEnabled
              showsHorizontalScrollIndicator={false}
              onMomentumScrollEnd={(e) => {
                const idx = Math.round(e.nativeEvent.contentOffset.x / width);
                setActiveImage(idx);
              }}
              renderItem={({ item, index }) => (
                <Image
                  source={{ uri: item.large ?? item.url }}
                  style={{ width, height: width * 0.75 }}
                  contentFit="cover"
                  transition={250}
                  priority={index === 0 ? 'high' : 'normal'}
                  accessibilityLabel={`Photo ${index + 1} sur ${ad.images.length} — ${ad.title}`}
                />
              )}
            />
            {ad.images.length > 1 && (
              <XStack
                position="absolute"
                bottom={12}
                alignSelf="center"
                gap="$2"
                paddingHorizontal="$3"
                paddingVertical="$1"
                borderRadius={999}
                backgroundColor="rgba(0,0,0,0.45)"
              >
                {ad.images.map((_, idx) => (
                  <YStack
                    key={`dot-${idx}`}
                    width={idx === activeImage ? 18 : 6}
                    height={6}
                    borderRadius={3}
                    backgroundColor="white"
                    opacity={idx === activeImage ? 1 : 0.5}
                  />
                ))}
              </XStack>
            )}
          </YStack>

          {/* Title + price */}
          <YStack padding="$4" gap="$3">
            <YStack gap="$1">
              <H2>{ad.title}</H2>
              {locationLabel && (
                <Paragraph color="$slate500" size="$4">
                  {locationLabel}
                </Paragraph>
              )}
            </YStack>

            <XStack alignItems="baseline" gap="$2">
              <H2 color="$brand">
                {ad.price != null ? `${ad.price.toLocaleString('fr-FR')} FCFA` : '—'}
              </H2>
              {ad.price != null && ad.transaction_type === 'location' && (
                <Paragraph color="$slate500" size="$4">
                  {periodLabel}
                </Paragraph>
              )}
            </XStack>

            {/* Key facts row */}
            <XStack flexWrap="wrap" gap="$2">
              {ad.bedrooms != null && (
                <KeyFact label={t('ad.bedrooms')} value={String(ad.bedrooms)} />
              )}
              {ad.bathrooms != null && (
                <KeyFact label={t('ad.bathrooms')} value={String(ad.bathrooms)} />
              )}
              {ad.surface_area != null && (
                <KeyFact label={t('ad.surface')} value={`${ad.surface_area} m²`} />
              )}
              {ad.has_parking && <KeyFact label={t('ad.parking')} value="Oui" />}
              {ad.keyscore != null && (
                <KeyFact label={t('ad.keyscore')} value={String(ad.keyscore)} highlight />
              )}
            </XStack>

            <Separator />

            <YStack gap="$2">
              <H4>{t('ad.description')}</H4>
              <Paragraph color="$slate700" size="$4">
                {ad.description}
              </Paragraph>
            </YStack>

            <Separator />

            <YStack gap="$2">
              <H4>{t('ad.location')}</H4>
              {ad.is_unlocked ? (
                <Paragraph color="$slate700" size="$4">
                  {ad.adresse}
                </Paragraph>
              ) : (
                <Paragraph color="$slate500" size="$3" fontStyle="italic">
                  {t('ad.unlockToSeeAddress')}
                </Paragraph>
              )}
            </YStack>
          </YStack>
        </ScrollView>

        {/* Fixed bottom CTA */}
        <YStack
          position="absolute"
          bottom={0}
          left={0}
          right={0}
          paddingHorizontal="$4"
          paddingTop="$3"
          paddingBottom={insets.bottom + 12}
          backgroundColor="$background"
          borderTopWidth={1}
          borderTopColor="$borderColor"
        >
          <Button
            size="$5"
            backgroundColor="$brand"
            color="$brandText"
            fontWeight="700"
            onPress={handleContact}
            accessibilityRole="button"
          >
            {t('ad.contactOwner')}
          </Button>
        </YStack>
      </YStack>
    </>
  );
}

function KeyFact({
  label,
  value,
  highlight,
}: {
  label: string;
  value: string;
  highlight?: boolean;
}) {
  return (
    <YStack
      paddingHorizontal="$3"
      paddingVertical="$2"
      borderRadius={8}
      backgroundColor={highlight ? '$brandAlpha10' : '$slate100'}
      minWidth={84}
    >
      <Paragraph size="$2" color="$slate500">
        {label}
      </Paragraph>
      <Paragraph size="$4" fontWeight="700" color={highlight ? '$brand' : '$slate700'}>
        {value}
      </Paragraph>
    </YStack>
  );
}
