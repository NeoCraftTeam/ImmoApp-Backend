import { CalendarClock, ChevronRight } from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useMyAds } from '@/hooks/useMyAds';
import { brand } from '@/theme/tokens';

export default function AvailabilityIndexScreen() {
  const router = useRouter();
  const { isAuthenticated } = useSession();
  const { data, isLoading, isRefetching, refetch } = useMyAds(
    {},
    isAuthenticated,
  );

  const ads = Array.isArray(data?.pages)
    ? data!.pages.flatMap((p) => (Array.isArray(p?.data) ? p.data : []))
    : [];

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader
        title="Disponibilités"
        subtitle="Configurez vos créneaux de visite par annonce"
      />

      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 10 }}
        refreshControl={
          <RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />
        }
      >
        {isLoading ? (
          <YStack height={320} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : ads.length === 0 ? (
          <YStack height={320}>
            <EmptyState
              icon={<CalendarClock size={28} color={brand.primary} />}
              title="Aucune annonce"
              hint="Créez une annonce pour configurer ses créneaux de visite."
            />
          </YStack>
        ) : (
          ads.map((ad) => {
            const thumb = ad.images?.[0]?.url;
            return (
              <Pressable
                key={ad.id}
                onPress={() =>
                  router.push(`/availability/${ad.id}` as never)
                }
              >
                <XStack
                  padding={12}
                  gap={12}
                  borderRadius={14}
                  borderWidth={1}
                  borderColor="$slate300"
                  alignItems="center"
                >
                  <YStack width={56} height={56} borderRadius={10} overflow="hidden" backgroundColor="$slate100">
                    {thumb ? (
                      <Image source={{ uri: thumb }} style={{ width: '100%', height: '100%' }} contentFit="cover" />
                    ) : null}
                  </YStack>
                  <YStack flex={1} gap={3}>
                    <Paragraph fontSize={14} fontWeight="700" color="$slate900" numberOfLines={1}>
                      {ad.title}
                    </Paragraph>
                    <Paragraph fontSize={11.5} color="$slate500" numberOfLines={1}>
                      {ad.adresse ?? '—'}
                    </Paragraph>
                  </YStack>
                  <ChevronRight size={18} color={brand.slate500} />
                </XStack>
              </Pressable>
            );
          })
        )}
      </ScrollView>
    </YStack>
  );
}
