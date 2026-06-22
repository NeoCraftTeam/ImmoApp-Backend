import { Star } from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import { useMemo } from 'react';
import { useQueries } from '@tanstack/react-query';
import { RefreshControl, ScrollView } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { apiClient } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useMyAds } from '@/hooks/useMyAds';
import { brand } from '@/theme/tokens';
import { formatDate } from '@/utils/format';
import type { Review } from '@/types/owner';

function StarRow({ rating }: { rating: number }) {
  return (
    <XStack gap={2}>
      {Array.from({ length: 5 }).map((_, i) => (
        <Star
          key={i}
          size={13}
          color={i < rating ? brand.accent : brand.slate300}
          fill={i < rating ? brand.accent : 'transparent'}
        />
      ))}
    </XStack>
  );
}

interface ReviewWithAd extends Review {
  ad: { id: string; title: string };
}

export default function ReviewsScreen() {
  const { isAuthenticated } = useSession();
  const ads = useMyAds({}, isAuthenticated);
  const flatAds = useMemo(
    () =>
      Array.isArray(ads.data?.pages)
        ? ads.data!.pages.flatMap((p) => (Array.isArray(p?.data) ? p.data : []))
        : [],
    [ads.data],
  );

  /**
   * Le backend n'expose pas `/my/reviews` agrégé — on fan-out un GET par
   * annonce et on agrège côté client. Pour de gros volumes ce serait un
   * endpoint dédié, mais pour <50 annonces c'est acceptable et permet
   * d'envoyer reviews + ad title pour grouper l'affichage.
   */
  const reviewQueries = useQueries({
    queries: flatAds.slice(0, 30).map((ad) => ({
      queryKey: ['ad-reviews', ad.id],
      queryFn: async () => {
        const { data } = await apiClient.get<{ data?: Review[] }>(
          ENDPOINTS.reviews.forAd(ad.id),
          { params: { per_page: 20 } },
        );
        const reviews = Array.isArray(data?.data) ? data!.data : [];
        return reviews.map((r) => ({
          ...r,
          ad: { id: ad.id, title: ad.title },
        }) as ReviewWithAd);
      },
      enabled: isAuthenticated && !!ad.id,
      staleTime: 60 * 1000,
    })),
  });

  const allReviews: ReviewWithAd[] = reviewQueries
    .flatMap((q) => (q.data ?? []) as ReviewWithAd[])
    .sort((a, b) => {
      const ta = a.created_at ? new Date(a.created_at).getTime() : 0;
      const tb = b.created_at ? new Date(b.created_at).getTime() : 0;
      return tb - ta;
    });

  const avg = allReviews.length
    ? allReviews.reduce((acc, r) => acc + (r.rating ?? 0), 0) / allReviews.length
    : 0;

  const isLoading = ads.isLoading || reviewQueries.some((q) => q.isLoading);
  const refreshing = ads.isRefetching || reviewQueries.some((q) => q.isRefetching);
  const onRefresh = () => {
    ads.refetch();
    reviewQueries.forEach((q) => q.refetch());
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Avis reçus" />

      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 12 }}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={brand.primary} />
        }
      >
        {/* Header stats */}
        {!isLoading && allReviews.length > 0 ? (
          <YStack
            padding={16}
            gap={6}
            borderRadius={16}
            backgroundColor={brand.accentAlpha10}
            alignItems="center"
          >
            <XStack alignItems="center" gap={6}>
              <Star size={20} color={brand.accent} fill={brand.accent} />
              <Paragraph fontSize={26} fontWeight="900" color="$slate900">
                {avg.toFixed(1)}
              </Paragraph>
              <Paragraph fontSize={13} color="$slate500">
                / 5
              </Paragraph>
            </XStack>
            <Paragraph fontSize={12.5} color="$slate700">
              {allReviews.length} avis sur l'ensemble de vos annonces
            </Paragraph>
          </YStack>
        ) : null}

        {isLoading ? (
          <YStack height={300} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : allReviews.length === 0 ? (
          <YStack height={300}>
            <EmptyState
              icon={<Star size={28} color={brand.accent} />}
              title="Aucun avis pour le moment"
              hint="Les avis laissés par les locataires apparaîtront ici. Encouragez vos clients satisfaits à partager leur expérience."
            />
          </YStack>
        ) : (
          allReviews.map((r) => {
            const fullName = `${r.user?.firstname ?? ''} ${r.user?.lastname ?? ''}`.trim();
            return (
              <YStack
                key={r.id}
                padding={14}
                gap={8}
                borderRadius={14}
                borderWidth={1}
                borderColor="$slate300"
              >
                <XStack alignItems="center" gap={10}>
                  <YStack width={36} height={36} borderRadius={18} overflow="hidden" backgroundColor="$slate100" alignItems="center" justifyContent="center">
                    {r.user?.avatar ? (
                      <Image source={{ uri: r.user.avatar }} style={{ width: '100%', height: '100%' }} contentFit="cover" />
                    ) : (
                      <Paragraph fontSize={13} fontWeight="800" color={brand.primary}>
                        {(r.user?.firstname?.[0] ?? '?').toUpperCase()}
                      </Paragraph>
                    )}
                  </YStack>
                  <YStack flex={1} gap={2}>
                    <Paragraph fontSize={13.5} fontWeight="700" color="$slate900">
                      {fullName || 'Anonyme'}
                    </Paragraph>
                    <Paragraph fontSize={11} color="$slate500">
                      {formatDate(r.created_at)} · {r.ad.title}
                    </Paragraph>
                  </YStack>
                  <StarRow rating={r.rating ?? 0} />
                </XStack>
                {r.comment ? (
                  <Paragraph fontSize={13} color="$slate700" lineHeight={19}>
                    {r.comment}
                  </Paragraph>
                ) : null}
                {r.response ? (
                  <YStack
                    marginLeft={46}
                    padding={10}
                    borderRadius={10}
                    backgroundColor={brand.primaryAlpha10}
                    gap={3}
                  >
                    <Paragraph fontSize={11.5} fontWeight="800" color={brand.primary}>
                      Votre réponse
                    </Paragraph>
                    <Paragraph fontSize={12.5} color="$slate900">
                      {r.response}
                    </Paragraph>
                  </YStack>
                ) : null}
              </YStack>
            );
          })
        )}
      </ScrollView>
    </YStack>
  );
}
