import { AlertCircle, Heart, LogIn } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { FlatList, RefreshControl } from 'react-native';
import { H2, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AdCard } from '@/components/AdCard';
import { AdCardSkeleton } from '@/components/AdCardSkeleton';
import { EmptyState } from '@/components/EmptyState';
import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { useFavorites } from '@/hooks/useFavorites';
import { t } from '@/i18n';

/**
 * Favorites tab. Three states:
 *
 *   1. Guest      → friendly sign-in prompt, no API call fired
 *   2. Authed +   → query runs; spinner → list (or empty state)
 *      data
 *   3. Error      → French message + retry button via refetch
 *
 * We deliberately render the tab as visible to guests instead of
 * gating it at the layout — discovering "where favorites would live"
 * is part of the conversion flow.
 */
export default function FavoritesTab() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { isAuthenticated } = useSession();

  const {
    data: favorites,
    isLoading,
    isError,
    error,
    refetch,
    isRefetching,
  } = useFavorites(isAuthenticated);

  if (!isAuthenticated) {
    return (
      <YStack flex={1} backgroundColor="$background" paddingTop={insets.top + 32} paddingHorizontal="$5">
        <H2>{t('favorites.title')}</H2>
        <EmptyState
          icon={<LogIn size={32} color="$slate500" />}
          title={t('favorites.signInPrompt')}
          body={t('favorites.emptyHint')}
          action={{ label: t('account.signIn'), onPress: () => router.push('/(auth)/login') }}
        />
      </YStack>
    );
  }

  if (isLoading) {
    return (
      <FlatList
        data={Array.from({ length: 6 }, (_, i) => i)}
        keyExtractor={(i) => `skel-${i}`}
        numColumns={2}
        columnWrapperStyle={{ gap: 12, marginBottom: 16 }}
        contentContainerStyle={{
          paddingTop: insets.top + 8,
          paddingBottom: insets.bottom + 16,
          paddingHorizontal: 12,
        }}
        ListHeaderComponent={
          <YStack paddingVertical="$3"><H2>{t('favorites.title')}</H2></YStack>
        }
        renderItem={() => (
          <YStack flex={1}><AdCardSkeleton /></YStack>
        )}
        scrollEnabled={false}
      />
    );
  }

  if (isError) {
    return (
      <YStack flex={1} backgroundColor="$background" paddingTop={insets.top + 12} paddingHorizontal="$5">
        <H2>{t('favorites.title')}</H2>
        <EmptyState
          icon={<AlertCircle size={32} color="$slate500" />}
          title={extractApiErrorMessage(error)}
          action={{ label: t('common.retry'), onPress: () => refetch() }}
        />
      </YStack>
    );
  }

  return (
    <FlatList
      data={favorites ?? []}
      keyExtractor={(item) => item.id}
      numColumns={2}
      columnWrapperStyle={{ gap: 12, marginBottom: 16 }}
      contentContainerStyle={{
        paddingTop: insets.top + 8,
        paddingBottom: insets.bottom + 16,
        paddingHorizontal: 12,
      }}
      ListHeaderComponent={
        <YStack paddingVertical="$3" gap="$1">
          <H2>{t('favorites.title')}</H2>
        </YStack>
      }
      ListEmptyComponent={
        <EmptyState
          icon={<Heart size={32} color="$slate500" />}
          title={t('favorites.empty')}
          body={t('favorites.emptyHint')}
          action={{ label: 'Découvrir les annonces', onPress: () => router.replace('/(tabs)/home') }}
        />
      }
      renderItem={({ item, index }) => (
        <YStack flex={1}>
          <AdCard ad={item} priority={index < 3} />
        </YStack>
      )}
      refreshControl={
        <RefreshControl refreshing={isRefetching} onRefresh={() => refetch()} />
      }
      removeClippedSubviews
      initialNumToRender={6}
    />
  );
}
