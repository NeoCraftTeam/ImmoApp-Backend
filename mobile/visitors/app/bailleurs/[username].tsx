import {
  ArrowLeft,
  CheckCircle2,
  MessageCircle,
  Star,
  UserPlus,
  UserCheck,
} from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
} from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { AdCard } from '@/components/AdCard';
import { MarkdownText } from '@/components/MarkdownText';
import { useBailleur, useBailleurFollow } from '@/hooks/useBailleur';
import { useSession } from '@/auth/SessionProvider';
import { resolveMediaUrl } from '@/lib/media-url';
import { brand } from '@/theme/tokens';

/**
 * Public landlord profile — accessible from the publisher row on any
 * ad-detail screen. Surfaces the trust signals (verified, trust score,
 * rating) plus the bailleur's active listings. Anonymous visitors can
 * read but follow + contact prompt sign-in.
 */
export default function BailleurProfile() {
  const { username } = useLocalSearchParams<{ username: string }>();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const { data, isLoading, isError, error } = useBailleur(username);
  const follow = useBailleurFollow(username);

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

  if (isError || !data) {
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
        <Button onPress={() => router.back()}>Retour</Button>
      </YStack>
    );
  }

  const firstName = data.firstname?.trim() ?? '';
  const initial = firstName.charAt(0).toUpperCase() || '?';
  const memberSince = data.created_at
    ? formatDistanceToNow(new Date(data.created_at), {
        addSuffix: true,
        locale: fr,
      })
    : null;

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$background">
        <XStack
          paddingTop={insets.top + 8}
          paddingHorizontal={14}
          paddingBottom={10}
          alignItems="center"
          gap={10}
          borderBottomWidth={1}
          borderBottomColor="$slate300"
        >
          <Pressable onPress={() => router.back()} hitSlop={8} accessibilityRole="button" accessibilityLabel="Retour">
            <YStack
              width={36}
              height={36}
              borderRadius={18}
              backgroundColor="$slate100"
              alignItems="center"
              justifyContent="center"
            >
              <ArrowLeft size={18} color="$slate700" />
            </YStack>
          </Pressable>
          <Paragraph fontSize={16} fontWeight="700" color="$slate900" flex={1}>
            {firstName || 'Bailleur'}
          </Paragraph>
        </XStack>

        <FlatList
          data={data.ads ?? []}
          keyExtractor={(item) => item.id}
          numColumns={2}
          columnWrapperStyle={{ gap: 12, marginBottom: 16 }}
          contentContainerStyle={{
            paddingHorizontal: 12,
            paddingBottom: insets.bottom + 24,
          }}
          ListHeaderComponent={
            <YStack paddingHorizontal={8} paddingVertical={18} gap={16}>
              {/* En-tête façon Instagram : avatar à gauche, nom + boutons
                  Suivre / Message directement à côté. */}
              <XStack alignItems="center" gap={16}>
                <YStack
                  width={84}
                  height={84}
                  borderRadius={42}
                  overflow="hidden"
                  backgroundColor={brand.primaryAlpha10}
                  alignItems="center"
                  justifyContent="center"
                >
                  {resolveMediaUrl(data.avatar) ? (
                    <Image
                      source={{ uri: resolveMediaUrl(data.avatar)! }}
                      style={{ width: '100%', height: '100%' }}
                      contentFit="cover"
                      accessibilityLabel={firstName || 'Bailleur'}
                    />
                  ) : (
                    <Paragraph fontSize={34} fontWeight="800" color={brand.primary}>
                      {initial}
                    </Paragraph>
                  )}
                </YStack>
                <YStack flex={1} gap={10}>
                  <YStack gap={2}>
                    <XStack alignItems="center" gap={6}>
                      <H2 fontSize={20} fontWeight="700" color="$slate900" numberOfLines={1}>
                        {firstName || 'Bailleur'}
                      </H2>
                      {data.is_verified && (
                        <CheckCircle2 size={18} color={brand.success} />
                      )}
                    </XStack>
                    {data.city ? (
                      <Paragraph fontSize={13} color="$slate500" numberOfLines={1}>
                        {data.city}
                      </Paragraph>
                    ) : null}
                  </YStack>
                  <XStack gap={8}>
                    <Button
                      flex={1}
                      size="$3"
                      backgroundColor={follow.data?.is_following ? '$slate100' : '$brand'}
                      color={follow.data?.is_following ? '$slate900' : 'white'}
                      fontWeight="700"
                      borderRadius={10}
                      paddingHorizontal={8}
                      onPress={() => {
                        if (!isAuthenticated) {
                          router.push('/(auth)/login');
                          return;
                        }
                        follow.toggle();
                      }}
                      icon={
                        follow.data?.is_following ? (
                          <UserCheck size={15} color="$slate900" />
                        ) : (
                          <UserPlus size={15} color="white" />
                        )
                      }
                    >
                      {follow.data?.is_following ? 'Suivi' : 'Suivre'}
                    </Button>
                    <Button
                      flex={1}
                      size="$3"
                      backgroundColor="$slate100"
                      color="$slate900"
                      fontWeight="700"
                      borderRadius={10}
                      paddingHorizontal={8}
                      onPress={() => {
                        if (!isAuthenticated) {
                          router.push('/(auth)/login');
                          return;
                        }
                        router.push('/messages');
                      }}
                      icon={<MessageCircle size={15} color="$slate900" />}
                    >
                      Message
                    </Button>
                  </XStack>
                </YStack>
              </XStack>

              {/* Trust signals row */}
              <XStack gap={10} flexWrap="wrap">
                {data.rating != null && (
                  <TrustChip
                    icon={<Star size={14} color={brand.warning} fill={brand.warning} />}
                    label={`${data.rating.toFixed(1)} (${data.reviews_count ?? 0})`}
                  />
                )}
                {(data.follower_count ?? 0) > 0 && (
                  <TrustChip
                    label={`${data.follower_count} abonné${(data.follower_count ?? 0) > 1 ? 's' : ''}`}
                  />
                )}
                {memberSince && (
                  <TrustChip label={`Membre depuis ${memberSince}`} />
                )}
              </XStack>

              {data.bio && <MarkdownText>{data.bio}</MarkdownText>}
            </YStack>
          }
          ListEmptyComponent={null}
          renderItem={({ item, index }) => (
            <YStack flex={1}>
              <AdCard ad={item} priority={index < 2} />
            </YStack>
          )}
        />
      </YStack>
    </>
  );
}

function TrustChip({
  icon,
  label,
}: {
  icon?: React.ReactNode;
  label: string;
}) {
  return (
    <XStack
      alignItems="center"
      gap={4}
      paddingHorizontal={10}
      paddingVertical={5}
      borderRadius={999}
      backgroundColor="$slate100"
    >
      {icon}
      <Paragraph fontSize={12} fontWeight="700" color="$slate700">
        {label}
      </Paragraph>
    </XStack>
  );
}
