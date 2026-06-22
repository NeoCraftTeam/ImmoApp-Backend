import { Building2, Plus, Search, X } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { FlatList, Pressable, RefreshControl, TextInput } from 'react-native';
import { H1, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { OwnerAdCard } from '@/components/OwnerAdCard';
import { useDebounce } from '@/hooks/useDebounce';
import { useMyAds } from '@/hooks/useMyAds';
import { AD_STATUS_META, brand } from '@/theme/tokens';
import { t } from '@/i18n';
import type { Ad } from '@/types/ad';

const STATUS_FILTERS = ['', 'draft', 'available', 'pending', 'reserved', 'rent', 'sold'] as const;

export default function AdsScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();

  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const debouncedSearch = useDebounce(search, 350);

  const filters = useMemo(
    () => ({ q: debouncedSearch || undefined, status: status || undefined }),
    [debouncedSearch, status],
  );

  const {
    data,
    isLoading,
    isRefetching,
    refetch,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useMyAds(filters, isAuthenticated);

  const ads = useMemo<Ad[]>(
    () => data?.pages.flatMap((p) => p.data ?? []) ?? [],
    [data],
  );

  return (
    <YStack flex={1} backgroundColor="$background">
      {/* Header */}
      <YStack paddingTop={insets.top + 12} paddingHorizontal={16} paddingBottom={12} gap={12}>
        <XStack alignItems="center" justifyContent="space-between">
          <H1 fontSize={26} fontWeight="900">
            {t('ads.title')}
          </H1>
        </XStack>

        {/* Search */}
        <XStack
          alignItems="center"
          gap={8}
          backgroundColor="$slate100"
          borderRadius={12}
          paddingHorizontal={12}
          height={44}
        >
          <Search size={18} color={brand.slate500} />
          <TextInput
            value={search}
            onChangeText={setSearch}
            placeholder={t('ads.search')}
            placeholderTextColor={brand.slate500}
            style={{ flex: 1, fontSize: 15, color: brand.slate900 }}
            returnKeyType="search"
          />
          {search.length > 0 ? (
            <Pressable onPress={() => setSearch('')} hitSlop={8}>
              <X size={16} color={brand.slate500} />
            </Pressable>
          ) : null}
        </XStack>

        {/* Status filter chips */}
        <FlatList
          data={STATUS_FILTERS as unknown as string[]}
          horizontal
          showsHorizontalScrollIndicator={false}
          keyExtractor={(s) => s || 'all'}
          contentContainerStyle={{ gap: 8 }}
          renderItem={({ item }) => {
            const active = status === item;
            const label = item === '' ? t('ads.filterAll') : AD_STATUS_META[item]?.label ?? item;
            return (
              <Pressable onPress={() => setStatus(item)}>
                <XStack
                  paddingHorizontal={14}
                  paddingVertical={8}
                  borderRadius={999}
                  backgroundColor={active ? brand.primary : brand.slate100}
                >
                  <Paragraph fontSize={13} fontWeight="700" color={active ? 'white' : brand.slate700}>
                    {label}
                  </Paragraph>
                </XStack>
              </Pressable>
            );
          }}
        />
      </YStack>

      {/* List */}
      {isLoading ? (
        <YStack flex={1} alignItems="center" justifyContent="center">
          <Spinner color={brand.primary} size="large" />
        </YStack>
      ) : (
        <FlatList
          data={ads}
          keyExtractor={(item) => item.id}
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 120, gap: 10 }}
          refreshControl={
            <RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />
          }
          renderItem={({ item }) => <OwnerAdCard ad={item} />}
          onEndReached={() => {
            if (hasNextPage && !isFetchingNextPage) fetchNextPage();
          }}
          onEndReachedThreshold={0.4}
          ListFooterComponent={
            isFetchingNextPage ? (
              <YStack paddingVertical={16} alignItems="center">
                <Spinner color={brand.primary} />
              </YStack>
            ) : null
          }
          ListEmptyComponent={
            <YStack height={420}>
              <EmptyState
                icon={<Building2 size={28} color={brand.primary} />}
                title={t('ads.empty')}
                hint={t('ads.emptyHint')}
                ctaLabel={t('ads.create')}
                onPressCta={() => router.push('/ads/new' as never)}
              />
            </YStack>
          }
        />
      )}

      {/* FAB */}
      <Pressable
        onPress={() => router.push('/ads/new' as never)}
        style={{
          position: 'absolute',
          right: 18,
          bottom: insets.bottom + 18,
        }}
      >
        <XStack
          width={56}
          height={56}
          borderRadius={28}
          backgroundColor={brand.primary}
          alignItems="center"
          justifyContent="center"
          shadowColor="rgba(13,148,136,0.4)"
          shadowOffset={{ width: 0, height: 6 }}
          shadowOpacity={1}
          shadowRadius={12}
          elevation={8}
        >
          <Plus size={28} color="white" />
        </XStack>
      </Pressable>
    </YStack>
  );
}
