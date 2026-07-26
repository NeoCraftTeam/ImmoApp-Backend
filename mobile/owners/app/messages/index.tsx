import { MessageCircle } from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useConversations } from '@/hooks/useConversations';
import { brand } from '@/theme/tokens';

function timeAgo(iso?: string): string {
  if (!iso) return '';
  const date = new Date(iso);
  const diff = Date.now() - date.getTime();
  if (Number.isNaN(diff)) return '';
  const m = Math.floor(diff / 60000);
  if (m < 1) return "à l'instant";
  if (m < 60) return `il y a ${m} min`;
  const h = Math.floor(m / 60);
  if (h < 24) return `il y a ${h} h`;
  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}

export default function MessagesScreen() {
  const router = useRouter();
  const { isAuthenticated } = useSession();
  const { data: list = [], isLoading, isRefetching, refetch } = useConversations(isAuthenticated);

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Messages" subtitle="Conversations avec vos prospects et locataires" />
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 8 }}
        refreshControl={
          <RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />
        }
      >
        {isLoading ? (
          <YStack height={320} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : list.length === 0 ? (
          <YStack height={320}>
            <EmptyState
              icon={<MessageCircle size={28} color={brand.primary} />}
              title="Aucun message"
              hint="Les conversations entamées par vos prospects apparaîtront ici."
            />
          </YStack>
        ) : (
          list.map((c) => {
            const fullName = c.other_participant?.name?.trim() ?? '';
            const preview = c.last_message?.body ?? 'Pas encore de message';
            const isUnread = c.unread_count > 0;
            return (
              <Pressable
                key={c.uuid}
                onPress={() => router.push(`/messages/${c.uuid}` as never)}
              >
                <XStack
                  padding={12}
                  gap={12}
                  borderRadius={14}
                  alignItems="center"
                  backgroundColor={isUnread ? brand.primaryAlpha10 : '$background'}
                  borderWidth={1}
                  borderColor={isUnread ? brand.primaryAlpha20 : '$slate300'}
                >
                  <YStack width={48} height={48} borderRadius={24} overflow="hidden" backgroundColor="$slate100" alignItems="center" justifyContent="center">
                    {c.other_participant?.avatar ? (
                      <Image source={{ uri: c.other_participant.avatar }} style={{ width: '100%', height: '100%' }} contentFit="cover" />
                    ) : (
                      <Paragraph fontSize={16} fontWeight="800" color={brand.primary}>
                        {(c.other_participant?.name?.[0] ?? '?').toUpperCase()}
                      </Paragraph>
                    )}
                  </YStack>
                  <YStack flex={1} gap={3}>
                    <XStack alignItems="center" gap={6}>
                      <Paragraph fontSize={14} fontWeight={isUnread ? '800' : '700'} color="$slate900" flex={1} numberOfLines={1}>
                        {fullName || 'Conversation'}
                      </Paragraph>
                      <Paragraph fontSize={11} color="$slate500">
                        {timeAgo(c.last_message?.created_at ?? c.last_message_at ?? undefined)}
                      </Paragraph>
                    </XStack>
                    <XStack alignItems="center" gap={6}>
                      <Paragraph fontSize={12.5} color="$slate500" flex={1} numberOfLines={1} fontWeight={isUnread ? '700' : '400'}>
                        {preview}
                      </Paragraph>
                      {isUnread ? (
                        <YStack
                          minWidth={20}
                          height={20}
                          borderRadius={10}
                          backgroundColor={brand.primary}
                          alignItems="center"
                          justifyContent="center"
                          paddingHorizontal={6}
                        >
                          <Paragraph fontSize={10.5} fontWeight="800" color="white">
                            {c.unread_count}
                          </Paragraph>
                        </YStack>
                      ) : null}
                    </XStack>
                  </YStack>
                </XStack>
              </Pressable>
            );
          })
        )}
      </ScrollView>
    </YStack>
  );
}
