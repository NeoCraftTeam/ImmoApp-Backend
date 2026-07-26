import {
  ArrowUpDown,
  Filter,
  List,
  Map as MapIcon,
  MapPin,
  RefreshCw,
  Search as SearchIcon,
  X,
} from '@tamagui/lucide-icons';
import { useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Keyboard,
  Pressable,
  RefreshControl,
  ScrollView,
} from 'react-native';
import { H2, Input, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { AdCard } from '@/components/AdCard';
import { EmptyState } from '@/components/EmptyState';
import { FadeIn } from '@/components/FadeIn';
import { SearchFilterSheet } from '@/components/SearchFilterSheet';
import { SearchResultsMap } from '@/components/SearchResultsMap';
import { useAdFacets } from '@/hooks/useAdFacets';
import { useAdSearch } from '@/hooks/useAdSearch';
import { useCityAutocomplete } from '@/hooks/useCitiesAndTypes';
import { useDebounce } from '@/hooks/useDebounce';
import { t } from '@/i18n';
import { brand } from '@/theme/tokens';
import {
  DEFAULT_SORT,
  EMPTY_FILTERS,
  SORT_OPTIONS,
  activeFilterChips,
  activeFilterCount,
  removeFilterChip,
  searchParamsToState,
  type AdFilters,
  type AdSort,
  type FilterChipDescriptor,
} from '@/types/filters';

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
  const [sort, setSort] = useState<AdSort>(DEFAULT_SORT);
  const [sheetOpen, setSheetOpen] = useState(false);
  const [viewMode, setViewMode] = useState<'list' | 'map'>('list');
  const [inputFocused, setInputFocused] = useState(false);
  const blurTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
  const debouncedQuery = useDebounce(query, 350);

  // Préremplissage depuis la Home (hero search / recherche IA) — chaque
  // navigation avec des params écrase l'état courant, une seule fois par
  // jeu de params (la ref évite de ré-appliquer à chaque re-render).
  const navParams = useLocalSearchParams<Record<string, string | string[]>>();
  const appliedParamsKey = useRef<string | null>(null);
  useEffect(() => {
    const relevant = ['q', 'city', 'type', 'transaction_type', 'bedrooms', 'price_min', 'price_max', 'surface_min', 'parking', 'furnished'];
    const incoming: Record<string, string | string[] | undefined> = {};
    for (const key of relevant) {
      if (navParams[key] != null) incoming[key] = navParams[key];
    }
    if (Object.keys(incoming).length === 0) return;
    const paramsKey = JSON.stringify(incoming);
    if (appliedParamsKey.current === paramsKey) return;
    appliedParamsKey.current = paramsKey;
    const { query: nextQuery, filters: nextFilters } = searchParamsToState(incoming);
    setQuery(nextQuery);
    setFilters(nextFilters);
  }, [navParams]);

  const { data: citySuggestions } = useCityAutocomplete(
    inputFocused ? debouncedQuery : '',
  );

  const {
    data: results,
    isFetching,
    isLoading,
    isError,
    error,
    refetch,
    isRefetching,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useAdSearch(debouncedQuery, filters, sort);

  const openSortPicker = () => {
    Alert.alert('Trier par', undefined, [
      ...SORT_OPTIONS.map((opt) => ({
        text: opt.value === sort ? `✓ ${opt.label}` : opt.label,
        onPress: () => setSort(opt.value),
      })),
      { text: 'Annuler', style: 'cancel' as const },
    ]);
  };

  const handleEndReached = useCallback(() => {
    if (hasNextPage && !isFetchingNextPage) {
      fetchNextPage();
    }
  }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

  const selectCity = (name: string) => {
    if (blurTimeout.current) clearTimeout(blurTimeout.current);
    setFilters((f) => ({ ...f, city: name }));
    setQuery('');
    setInputFocused(false);
    Keyboard.dismiss();
  };

  const removeChip = (key: FilterChipDescriptor['key']) => {
    if (key === 'query') {
      setQuery('');
      return;
    }
    setFilters((f) => removeFilterChip(f, key));
  };

  const resetAll = () => {
    setFilters(EMPTY_FILTERS);
    setQuery('');
  };

  // Hiding on blur is delayed one tick so a tap on a suggestion row
  // (which blurs the input first) still lands before the list unmounts.
  const handleInputBlur = () => {
    blurTimeout.current = setTimeout(() => setInputFocused(false), 150);
  };

  const handleInputFocus = () => {
    if (blurTimeout.current) clearTimeout(blurTimeout.current);
    setInputFocused(true);
  };

  const filterCount = activeFilterCount(filters);
  const hasQuery = debouncedQuery.trim().length >= 2;
  const hasFilters = filterCount > 0;
  const isSearching = hasQuery || hasFilters;
  const showEmpty = isSearching && !isLoading && (results?.length ?? 0) === 0;
  const showSuggestions =
    inputFocused &&
    query.trim().length >= 1 &&
    (citySuggestions?.length ?? 0) > 0;
  const chips = activeFilterChips(filters, debouncedQuery);
  const { data: facets } = useAdFacets();
  const citySuggestionsForEmpty = (facets?.cities ?? [])
    .filter((c) => c.name !== filters.city)
    .slice(0, 3);

  return (
    <YStack flex={1} backgroundColor="$background">
      <YStack
        paddingTop={insets.top + 12}
        paddingHorizontal="$4"
        paddingBottom="$2"
        gap="$3"
        zIndex={30}
      >
        <H2>{t('search.title')}</H2>
        <YStack position="relative" zIndex={30}>
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
              onFocus={handleInputFocus}
              onBlur={handleInputBlur}
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

          <Pressable
            onPress={openSortPicker}
            hitSlop={6}
            accessibilityRole="button"
            accessibilityLabel="Trier les résultats"
          >
            <YStack
              width={44}
              height={44}
              borderRadius={12}
              borderWidth={1}
              borderColor={sort !== DEFAULT_SORT ? '$brand' : '$borderColor'}
              backgroundColor={sort !== DEFAULT_SORT ? '$brandAlpha10' : 'transparent'}
              alignItems="center"
              justifyContent="center"
            >
              <ArrowUpDown size={18} color={sort !== DEFAULT_SORT ? '$brand' : '$slate700'} />
            </YStack>
          </Pressable>

          <Pressable
            onPress={() => setViewMode((m) => (m === 'list' ? 'map' : 'list'))}
            hitSlop={6}
            accessibilityRole="button"
            accessibilityLabel={
              viewMode === 'list' ? 'Afficher la carte' : 'Afficher la liste'
            }
          >
            <YStack
              width={44}
              height={44}
              borderRadius={12}
              borderWidth={1}
              borderColor={viewMode === 'map' ? '$brand' : '$borderColor'}
              backgroundColor={viewMode === 'map' ? '$brandAlpha10' : 'transparent'}
              alignItems="center"
              justifyContent="center"
            >
              {viewMode === 'list' ? (
                <MapIcon size={18} color="$slate700" />
              ) : (
                <List size={18} color="$brand" />
              )}
            </YStack>
          </Pressable>
        </XStack>

        {showSuggestions && (
          <YStack
            position="absolute"
            top={50}
            left={0}
            right={0}
            zIndex={40}
            backgroundColor="$background"
            borderWidth={1}
            borderColor="$borderColor"
            borderRadius={12}
            maxHeight={260}
            overflow="hidden"
            shadowColor="#000"
            shadowOpacity={0.12}
            shadowRadius={16}
            shadowOffset={{ width: 0, height: 8 }}
            elevation={8}
          >
            <ScrollView keyboardShouldPersistTaps="handled">
              {(citySuggestions ?? []).map((city) => (
                <Pressable
                  key={city.id}
                  onPress={() => selectCity(city.name)}
                  accessibilityRole="button"
                  accessibilityLabel={`Rechercher à ${city.name}`}
                >
                  <XStack
                    alignItems="center"
                    gap={10}
                    paddingHorizontal={14}
                    paddingVertical={12}
                  >
                    <MapPin size={16} color="$slate500" />
                    <Paragraph fontSize={14} color="$slate900">
                      {city.name}
                    </Paragraph>
                  </XStack>
                </Pressable>
              ))}
            </ScrollView>
          </YStack>
        )}
        </YStack>

        {chips.length > 0 ? (
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            keyboardShouldPersistTaps="handled"
            contentContainerStyle={{ gap: 8, alignItems: 'center' }}
          >
            {chips.map((chip) => (
              <XStack
                key={chip.key}
                alignItems="center"
                gap={6}
                paddingHorizontal={12}
                paddingVertical={6}
                borderRadius={999}
                borderWidth={1}
                borderColor="$brand"
                backgroundColor="$brandAlpha10"
              >
                {chip.key === 'city' ? <MapPin size={14} color="$brand" /> : null}
                <Paragraph fontSize={13} fontWeight="600" color="$brand">
                  {chip.label}
                </Paragraph>
                <Pressable
                  onPress={() => removeChip(chip.key)}
                  hitSlop={8}
                  accessibilityRole="button"
                  accessibilityLabel={`Retirer le filtre ${chip.label}`}
                >
                  <X size={14} color="$brand" />
                </Pressable>
              </XStack>
            ))}
            <Pressable
              onPress={resetAll}
              hitSlop={6}
              accessibilityRole="button"
              accessibilityLabel="Réinitialiser tous les filtres"
            >
              <Paragraph
                fontSize={13}
                fontWeight="600"
                color="$slate500"
                paddingHorizontal={4}
              >
                {t('search.filters.reset')}
              </Paragraph>
            </Pressable>
          </ScrollView>
        ) : null}
      </YStack>

      {isError && (
        <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={12}>
          <Paragraph color="$slate700" textAlign="center" fontSize={14}>
            {extractApiErrorMessage(error)}
          </Paragraph>
          <Pressable
            onPress={() => refetch()}
            hitSlop={6}
            accessibilityRole="button"
            accessibilityLabel="Réessayer la recherche"
          >
            <XStack
              alignItems="center"
              gap={6}
              paddingHorizontal={14}
              paddingVertical={10}
              borderRadius={999}
              backgroundColor="$slate900"
            >
              <RefreshCw size={14} color="white" />
              <Paragraph fontSize={13} fontWeight="700" color="white">
                Réessayer
              </Paragraph>
            </XStack>
          </Pressable>
        </YStack>
      )}

      {!isError && viewMode === 'map' && (
        <SearchResultsMap ads={results ?? []} />
      )}

      {!isError && viewMode === 'list' && (
        <FlatList
          data={results ?? []}
          keyExtractor={(item) => item.id}
          numColumns={2}
          columnWrapperStyle={{ gap: 12, marginBottom: 16 }}
          contentContainerStyle={{
            paddingHorizontal: 12,
            paddingTop: 4,
            paddingBottom: insets.bottom + 16,
          }}
          ListEmptyComponent={
            showEmpty ? (
              <YStack gap="$3">
                <EmptyState
                  icon={<SearchIcon size={32} color="$slate500" />}
                  title={t('search.noResults')}
                  body="Essayez d'élargir vos critères ou de modifier votre recherche."
                />
                {citySuggestionsForEmpty.length > 0 ? (
                  <YStack gap="$2" alignItems="center">
                    <Paragraph fontSize={13} color="$slate500">
                      Explorer d'autres villes :
                    </Paragraph>
                    <XStack gap="$2" flexWrap="wrap" justifyContent="center">
                      {citySuggestionsForEmpty.map((city) => (
                        <Pressable
                          key={city.name}
                          onPress={() => selectCity(city.name)}
                          accessibilityRole="button"
                          accessibilityLabel={`Rechercher à ${city.name}`}
                        >
                          <XStack
                            alignItems="center"
                            gap={6}
                            paddingHorizontal={12}
                            paddingVertical={8}
                            borderRadius={999}
                            borderWidth={1}
                            borderColor="$borderColor"
                          >
                            <MapPin size={14} color="$slate500" />
                            <Paragraph fontSize={13} color="$slate700">
                              {city.name} ({city.count})
                            </Paragraph>
                          </XStack>
                        </Pressable>
                      ))}
                    </XStack>
                  </YStack>
                ) : null}
              </YStack>
            ) : null
          }
          renderItem={({ item, index }) => (
            <YStack flex={1}>
              <FadeIn delay={Math.min(index, 6) * 40}>
                <AdCard ad={item} priority={index < 3} />
              </FadeIn>
            </YStack>
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
          refreshControl={
            <RefreshControl
              refreshing={isRefetching}
              onRefresh={() => refetch()}
              tintColor={brand.primary}
              colors={[brand.primary]}
            />
          }
        />
      )}

      {isLoading && (
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

      {sheetOpen && (
        <SearchFilterSheet
          open={sheetOpen}
          onOpenChange={setSheetOpen}
          filters={filters}
          onApply={setFilters}
        />
      )}
    </YStack>
  );
}
