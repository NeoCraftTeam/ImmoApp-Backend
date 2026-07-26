import { ArrowLeft, X } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { Image } from 'expo-image';
import { Pressable, ScrollView } from 'react-native';
import { H4, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useCompare } from '@/providers/CompareProvider';
import { useCurrency } from '@/hooks/useCurrency';
import { t } from '@/i18n';
import type { Ad } from '@/types/ad';

const COLUMN_WIDTH = 156;

/**
 * Compare screen — mirrors the web `ComparisonTable`. Each ad gets a
 * column with a hero thumb + title + price + a list of key facts;
 * columns are laid out in a horizontal ScrollView so 4 ads on a
 * narrow phone don't squeeze unreadable.
 *
 * The criterion-label column on the LEFT is sticky-ish (it's outside
 * the ScrollView entirely) so the user always knows what they're
 * looking at as they swipe through compared columns.
 *
 * Removing an ad in-screen calls `toggle` from the compare context
 * which immediately reflows the screen; when the last ad is removed
 * the screen renders an empty state.
 */
const CRITERIA: Array<{
  label: string;
  render: (ad: Ad, formatPrice: (amountXAF: number) => string) => string;
}> = [
  {
    label: 'Prix',
    render: (ad, formatPrice) => (ad.price != null ? formatPrice(ad.price) : '—'),
  },
  {
    label: 'Transaction',
    render: (ad) =>
      ad.transaction_type === 'location'
        ? 'Location'
        : ad.transaction_type === 'vente'
          ? 'Vente'
          : '—',
  },
  {
    label: 'Surface',
    render: (ad) => (ad.surface_area != null ? `${ad.surface_area} m²` : '—'),
  },
  {
    label: 'Chambres',
    render: (ad) => (ad.bedrooms != null ? String(ad.bedrooms) : '—'),
  },
  {
    label: 'Salles de bain',
    render: (ad) => (ad.bathrooms != null ? String(ad.bathrooms) : '—'),
  },
  {
    label: 'Parking',
    render: (ad) => (ad.has_parking ? 'Oui' : 'Non'),
  },
  {
    label: 'KeyScore',
    render: (ad) => (ad.keyscore != null ? String(ad.keyscore) : '—'),
  },
  {
    label: 'Quartier',
    render: (ad) => ad.quarter?.name ?? '—',
  },
  {
    label: 'Ville',
    render: (ad) => ad.quarter?.city_name ?? '—',
  },
  {
    label: 'Visite 360°',
    render: (ad) => (ad.has_3d_tour ? 'Disponible' : '—'),
  },
];

export default function CompareScreen() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { items, toggle } = useCompare();
  const { format } = useCurrency();

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$background" paddingTop={insets.top + 8}>
        <XStack
          paddingHorizontal="$3"
          paddingBottom="$2"
          alignItems="center"
          gap="$3"
        >
          <Pressable
            onPress={() => router.back()}
            hitSlop={8}
            accessibilityRole="button"
            accessibilityLabel={t('common.back')}
          >
            <ArrowLeft size={22} color="$slate700" />
          </Pressable>
          <H4 flex={1}>{t('compare.title')}</H4>
        </XStack>

        {items.length === 0 ? (
          <YStack flex={1} justifyContent="center" alignItems="center" padding="$5" gap={12}>
            <YStack
              width={64}
              height={64}
              borderRadius={32}
              backgroundColor="$slate100"
              alignItems="center"
              justifyContent="center"
            >
              <X size={28} color="$slate500" />
            </YStack>
            <Paragraph color="$slate900" fontWeight="700" textAlign="center" fontSize={15}>
              {t('compare.empty')}
            </Paragraph>
            <Pressable
              onPress={() => router.replace('/(tabs)/search')}
              accessibilityRole="button"
              accessibilityLabel="Aller à la recherche"
            >
              <XStack
                paddingHorizontal={16}
                paddingVertical={10}
                borderRadius={999}
                backgroundColor="$brand"
              >
                <Paragraph color="white" fontWeight="700" fontSize={13}>
                  Parcourir les annonces
                </Paragraph>
              </XStack>
            </Pressable>
          </YStack>
        ) : (
          <XStack flex={1}>
            {/* Sticky left label column */}
            <YStack
              width={120}
              borderRightWidth={1}
              borderRightColor="$borderColor"
              backgroundColor="$slate100"
            >
              {/* Spacer matching the header height of the data columns */}
              <YStack height={144} />
              {CRITERIA.map((c, idx) => (
                <YStack
                  key={c.label}
                  paddingHorizontal="$2"
                  paddingVertical="$2"
                  backgroundColor={idx % 2 === 0 ? '$slate100' : '$background'}
                  borderBottomWidth={1}
                  borderBottomColor="$borderColor"
                >
                  <Paragraph size="$2" color="$slate500" fontWeight="600">
                    {c.label}
                  </Paragraph>
                </YStack>
              ))}
            </YStack>

            {/* Scrollable data columns */}
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={{ paddingBottom: insets.bottom + 16 }}
            >
              {items.map((ad) => {
                const cover = ad.images.find((i) => i.is_primary) ?? ad.images[0];
                return (
                  <YStack
                    key={ad.id}
                    width={COLUMN_WIDTH}
                    borderRightWidth={1}
                    borderRightColor="$borderColor"
                  >
                    {/* Header card */}
                    <YStack padding="$2" gap="$1" backgroundColor="$background">
                      <YStack
                        height={84}
                        borderRadius={8}
                        overflow="hidden"
                        backgroundColor="$slate100"
                        position="relative"
                      >
                        {cover?.thumb || cover?.url ? (
                          <Image
                            source={{ uri: cover.thumb ?? cover.url }}
                            style={{ width: '100%', height: '100%' }}
                            contentFit="cover"
                          />
                        ) : null}
                        <Pressable
                          onPress={() => toggle(ad)}
                          hitSlop={6}
                          style={{
                            position: 'absolute',
                            top: 4,
                            right: 4,
                            width: 22,
                            height: 22,
                            borderRadius: 11,
                            backgroundColor: 'rgba(0,0,0,0.55)',
                            alignItems: 'center',
                            justifyContent: 'center',
                          }}
                          accessibilityRole="button"
                          accessibilityLabel={`Retirer ${ad.title}`}
                        >
                          <X size={13} color="white" />
                        </Pressable>
                      </YStack>
                      <Paragraph size="$3" fontWeight="700" numberOfLines={2}>
                        {ad.title}
                      </Paragraph>
                    </YStack>

                    {/* Criterion rows */}
                    {CRITERIA.map((c, idx) => (
                      <YStack
                        key={c.label}
                        paddingHorizontal="$2"
                        paddingVertical="$2"
                        backgroundColor={
                          idx % 2 === 0 ? '$slate100' : '$background'
                        }
                        borderBottomWidth={1}
                        borderBottomColor="$borderColor"
                        alignItems="center"
                        justifyContent="center"
                        minHeight={42}
                      >
                        <Paragraph size="$3" textAlign="center" numberOfLines={1}>
                          {c.render(ad, format)}
                        </Paragraph>
                      </YStack>
                    ))}
                  </YStack>
                );
              })}
            </ScrollView>
          </XStack>
        )}
      </YStack>
    </>
  );
}
