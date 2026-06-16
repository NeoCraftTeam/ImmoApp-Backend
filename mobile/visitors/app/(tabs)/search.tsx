import { Filter, Search as SearchIcon } from '@tamagui/lucide-icons';
import { useCallback, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable } from 'react-native';
import { H2, Input, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AdCard } from '@/components/AdCard';
import { SearchFilterSheet } from '@/components/SearchFilterSheet';
import { useAdSearch } from '@/hooks/useAdSearch';
import { useDebounce } from '@/hooks/useDebounce';
import { t } from '@/i18n';
import { EMPTY_FILTERS, activeFilterCount, type AdFilters } from '@/types/filters';

/**
 * Search tab — single text input plus a filter sheet for the structural
 * narrowing (price range, surface, transaction type, ad type). Debounced
 * 350 ms on the text input so the user can keep typing without flooding
 * the network.
 *
 * The query stays enabled whenever EITHER a non-trivial text query OR
 * any active filter exists, so a power user can browse "all studios
 * under 250k FCFA in any location" without typing a single character.
 */
export default function SearchTab() {
  const insets = useSafeAreaInsets();
  const [query, setQuery] = useState('');
  const [filters, setFilters] = useState<AdFilters>(EMPTY_FILTERS);
  const [sheetOpen, setSheetOpen] = useState(false);
  const debouncedQuery = useDebounce(query, 350);

  const {
    data: results,
    isFetching,
    isLoading,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useAdSearch(debouncedQuery, filters);

  const handleEndReached = useCallback(() => {
    if (hasNextPage && !isFetchingNextPage) {
      fetchNextPage();
    }
  }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

  const filterCount = activeFilterCount(filters);
  const hasQuery = debouncedQuery.trim().length >= 2;
  const hasFilters = filterCount > 0;
  const isSearching = hasQuery || hasFilters;
  const showEmpty = isSearching && !isLoading && (results?.length ?? 0) === 0;

  return (
    <YStack flex={1} backgroundColor="$background">
      <YStack
        paddingTop={insets.top + 12}
        paddingHorizontal="$4"
        paddingBottom="$2"
        gap="$3"
      >
        <H2>{t('search.title')}</H2>
        <XStack gap="$2" alignItems="center">
          <XStack
            flex={1}
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

          <Pressable
            onPress={() => setSheetOpen(true)}
            hitSlop={6}
            accessibilityRole="button"
            accessibilityLabel={t('search.filterButton')}
            accessibilityState={{ expanded: sheetOpen }}
          >
            <YStack
              width={44}
              height={44}
              borderRadius={12}
              borderWidth={1}
              borderColor={hasFilters ? '$brand' : '$borderColor'}
              backgroundColor={hasFilters ? '$brandAlpha10' : 'transparent'}
              alignItems="center"
              justifyContent="center"
              position="relative"
            >
              <Filter size={18} color={hasFilters ? '$brand' : '$slate700'} />
              {hasFilters && (
                <YStack
                  position="absolute"
                  top={-4}
                  right={-4}
                  width={16}
                  height={16}
                  borderRadius={8}
                  backgroundColor="$brand"
                  alignItems="center"
                  justifyContent="center"
                >
                  <Paragraph size="$1" color="$brandText" fontWeight="700">
                    {filterCount}
                  </Paragraph>
                </YStack>
              )}
            </YStack>
          </Pressable>
        </XStack>
      </YStack>

      {!isSearching && (
        <YStack flex={1} justifyContent="center" alignItems="center" padding="$5">
          <Paragraph color="$slate500" size="$4" textAlign="center">
            {t('search.empty')}
          </Paragraph>
        </YStack>
      )}

      {isSearching && (
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

      {isLoading && isSearching && (
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

      <SearchFilterSheet
        open={sheetOpen}
        onOpenChange={setSheetOpen}
        filters={filters}
        onApply={setFilters}
      />
    </YStack>
  );
}
