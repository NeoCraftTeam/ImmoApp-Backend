import { Search as SearchIcon } from '@tamagui/lucide-icons';
import { useCallback, useState } from 'react';
import { ActivityIndicator, FlatList } from 'react-native';
import { H2, Input, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AdCard } from '@/components/AdCard';
import { useAdSearch } from '@/hooks/useAdSearch';
import { useDebounce } from '@/hooks/useDebounce';
import { t } from '@/i18n';

/**
 * Search tab — single text input, debounced, hits `/ads?q=...`. The
 * input is mounted on every tab visit (no persistent state across
 * sessions) which matches the typical "I want to look for something
 * right now" mental model.
 *
 * Why infinite scroll here too: search results can return 100+ ads
 * for popular queries (e.g. "Yaoundé"). FlatList with `onEndReached`
 * gives the user the same scroll feel as the home tab.
 *
 * Filter sheet (price range, surface, type, transaction) is intentionally
 * out of scope for v0.2 — we ship text search first, then iterate on
 * the filter UX after seeing real query patterns in production.
 */
export default function SearchTab() {
  const insets = useSafeAreaInsets();
  const [query, setQuery] = useState('');
  const debouncedQuery = useDebounce(query, 350);

  const {
    data: results,
    isFetching,
    isLoading,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useAdSearch(debouncedQuery, true);

  const handleEndReached = useCallback(() => {
    if (hasNextPage && !isFetchingNextPage) {
      fetchNextPage();
    }
  }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

  const hasQuery = debouncedQuery.trim().length >= 2;
  const showEmpty = hasQuery && !isLoading && (results?.length ?? 0) === 0;
  const showPrompt = !hasQuery;

  return (
    <YStack flex={1} backgroundColor="$background">
      <YStack
        paddingTop={insets.top + 12}
        paddingHorizontal="$4"
        paddingBottom="$2"
        gap="$3"
      >
        <H2>{t('search.title')}</H2>
        <XStack
          alignItems="center"
          gap="$2"
          paddingHorizontal="$3"
          borderWidth={1}
          borderColor="$borderColor"
          borderRadius={12}
          height={44}
          backgroundColor="$background"
        >
          <SearchIcon size={18} color="$slate500" />
          <Input
            flex={1}
            value={query}
            onChangeText={setQuery}
            placeholder={t('search.placeholder')}
            placeholderTextColor="$slate500"
            autoCapitalize="none"
            autoCorrect={false}
            unstyled
            size="$4"
            accessibilityLabel={t('search.placeholder')}
          />
        </XStack>
      </YStack>

      {showPrompt && (
        <YStack flex={1} justifyContent="center" alignItems="center" padding="$5">
          <Paragraph color="$slate500" size="$4" textAlign="center">
            {t('search.empty')}
          </Paragraph>
        </YStack>
      )}

      {hasQuery && (
        <FlatList
          data={results ?? []}
          keyExtractor={(item) => item.id}
          contentContainerStyle={{
            paddingHorizontal: 12,
            paddingTop: 4,
            paddingBottom: insets.bottom + 16,
          }}
          ListEmptyComponent={
            showEmpty ? (
              <YStack padding="$5" alignItems="center">
                <Paragraph color="$slate500">{t('search.noResults')}</Paragraph>
              </YStack>
            ) : null
          }
          renderItem={({ item, index }) => (
            <AdCard ad={item} priority={index < 3} />
          )}
          ListFooterComponent={
            isFetching && !isFetchingNextPage ? null : isFetchingNextPage ? (
              <YStack paddingVertical="$3" alignItems="center">
                <ActivityIndicator />
              </YStack>
            ) : null
          }
          onEndReached={handleEndReached}
          onEndReachedThreshold={0.5}
          removeClippedSubviews
          initialNumToRender={6}
        />
      )}

      {isLoading && hasQuery && (
        <YStack
          position="absolute"
          left={0}
          right={0}
          top={120}
          alignItems="center"
        >
          <ActivityIndicator />
        </YStack>
      )}
    </YStack>
  );
}
