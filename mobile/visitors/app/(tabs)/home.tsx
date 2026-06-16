import { useCallback } from 'react';
import { ActivityIndicator, FlatList, RefreshControl } from 'react-native';
import { H2, Paragraph, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AdCard } from '@/components/AdCard';
import { extractApiErrorMessage } from '@/api/client';
import { useAdFeed } from '@/hooks/useAdFeed';
import { t } from '@/i18n';

const TOP_PRIORITY_COUNT = 3;

/**
 * Home feed — the primary surface for browsing. Cursor-paginated via
 * TanStack Query; the FlatList drives `fetchNextPage()` when the user
 * scrolls within ~one page-height of the bottom. Pull-to-refresh
 * triggers a full refetch from the first page.
 *
 * The first three cards get `priority="high"` on their hero image so
 * the visual above-the-fold appears immediately on cold cache.
 *
 * Empty / error states use the existing i18n strings and a plain
 * paragraph centred on the screen — keeping the home screen text-only
 * during failures avoids the home page itself looking broken.
 */
export default function Home() {
  const insets = useSafeAreaInsets();

  const {
    data: ads,
    isLoading,
    isError,
    error,
    refetch,
    isRefetching,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useAdFeed(15);

  const handleEndReached = useCallback(() => {
    if (hasNextPage && !isFetchingNextPage) {
      fetchNextPage();
    }
  }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

  if (isLoading) {
    return (
      <YStack
        flex={1}
        backgroundColor="$background"
        justifyContent="center"
        alignItems="center"
      >
        <ActivityIndicator />
      </YStack>
    );
  }

  if (isError) {
    return (
      <YStack
        flex={1}
        backgroundColor="$background"
        justifyContent="center"
        alignItems="center"
        padding="$5"
        gap="$2"
      >
        <Paragraph size="$4" color="$slate700" textAlign="center">
          {extractApiErrorMessage(error)}
        </Paragraph>
      </YStack>
    );
  }

  return (
    <FlatList
      data={ads ?? []}
      keyExtractor={(item) => item.id}
      contentContainerStyle={{
        paddingTop: insets.top + 8,
        paddingBottom: insets.bottom + 16,
        paddingHorizontal: 12,
      }}
      ListHeaderComponent={
        <YStack paddingVertical="$3" gap="$1">
          <H2>{t('home.title')}</H2>
          <Paragraph color="$slate500" size="$4">
            {t('home.subtitle')}
          </Paragraph>
        </YStack>
      }
      ListEmptyComponent={
        <YStack padding="$5" alignItems="center">
          <Paragraph color="$slate500">{t('home.empty')}</Paragraph>
        </YStack>
      }
      renderItem={({ item, index }) => (
        <AdCard ad={item} priority={index < TOP_PRIORITY_COUNT} />
      )}
      refreshControl={
        <RefreshControl refreshing={isRefetching} onRefresh={refetch} />
      }
      onEndReached={handleEndReached}
      onEndReachedThreshold={0.5}
      ListFooterComponent={
        isFetchingNextPage ? (
          <YStack paddingVertical="$3" alignItems="center">
            <ActivityIndicator />
          </YStack>
        ) : null
      }
      removeClippedSubviews
      initialNumToRender={6}
      maxToRenderPerBatch={6}
      windowSize={11}
    />
  );
}
