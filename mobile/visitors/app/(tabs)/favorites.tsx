import { useRouter } from 'expo-router';
import { ActivityIndicator, FlatList, RefreshControl } from 'react-native';
import { Button, H2, Paragraph, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AdCard } from '@/components/AdCard';
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
      <YStack
        flex={1}
        backgroundColor="$background"
        paddingTop={insets.top + 32}
        paddingHorizontal="$5"
        gap="$5"
      >
        <YStack gap="$2">
          <H2>{t('favorites.title')}</H2>
        </YStack>
        <YStack flex={1} justifyContent="center" alignItems="center" gap="$4">
          <Paragraph color="$slate500" size="$4" textAlign="center">
            {t('favorites.signInPrompt')}
          </Paragraph>
          <Button
            size="$5"
            backgroundColor="$brand"
            color="$brandText"
            fontWeight="700"
            onPress={() => router.push('/(auth)/login')}
          >
            {t('account.signIn')}
          </Button>
        </YStack>
      </YStack>
    );
  }

  if (isLoading) {
    return (
      <YStack flex={1} backgroundColor="$background" justifyContent="center" alignItems="center">
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
        gap="$3"
      >
        <Paragraph color="$slate700" textAlign="center">
          {extractApiErrorMessage(error)}
        </Paragraph>
        <Button onPress={() => refetch()} size="$3">
          {t('common.retry')}
        </Button>
      </YStack>
    );
  }

  return (
    <FlatList
      data={favorites ?? []}
      keyExtractor={(item) => item.id}
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
        <YStack padding="$6" alignItems="center" gap="$2">
          <Paragraph color="$slate700" fontWeight="700">
            {t('favorites.empty')}
          </Paragraph>
          <Paragraph color="$slate500" size="$3" textAlign="center">
            {t('favorites.emptyHint')}
          </Paragraph>
        </YStack>
      }
      renderItem={({ item, index }) => <AdCard ad={item} priority={index < 3} />}
      refreshControl={
        <RefreshControl refreshing={isRefetching} onRefresh={() => refetch()} />
      }
      removeClippedSubviews
      initialNumToRender={6}
    />
  );
}
