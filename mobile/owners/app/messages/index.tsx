import { MessageCircle, RotateCw } from '@tamagui/lucide-icons';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { FlatList, Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import { Skeleton } from '@/components/Skeleton';
import { useConversations } from '@/hooks/useConversations';
import { resolveMediaUrl } from '@/lib/media-url';
import { brand } from '@/theme/tokens';
import type { ConversationPreview } from '@/types/conversation';
import { formatPresence } from '@/utils/presence';

/**
 * Inbox bailleur — conversations les plus récentes en tête. Non-lus :
 * fond teinté + pastille de comptage. Pastille verte « en ligne » ancrée
 * sur l'avatar (présence dérivée de `last_seen_at`).
 *
 * FlatList (recyclage natif) — jamais de ScrollView : la liste peut
 * grandir et le rendu intégral ferait chuter la fluidité.
 */
export default function MessagesScreen() {
  const router = useRouter();
  const { isAuthenticated } = useSession();
  const { data: list = [], isLoading, isError, error, isRefetching, refetch } =
    useConversations(isAuthenticated);

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Messages" subtitle="Conversations avec vos prospects et locataires" />

      {isLoading ? (
        <InboxSkeleton />
      ) : isError ? (
        <YStack flex={1} justifyContent="center" alignItems="center" padding="$5" gap={12}>
          <Paragraph color="$slate700" textAlign="center">
            {extractApiErrorMessage(error)}
          </Paragraph>
          <Pressable onPress={() => refetch()} hitSlop={6} accessibilityRole="button" accessibilityLabel="Réessayer">
            <XStack alignItems="center" gap={6} paddingHorizontal={16} paddingVertical={10} borderRadius={999} backgroundColor="$slate900">
              <RotateCw size={14} color="white" />
              <Paragraph fontSize={13} fontWeight="700" color="white">
                Réessayer
              </Paragraph>
            </XStack>
          </Pressable>
        </YStack>
      ) : (
        <FlatList
          data={list}
          keyExtractor={(item) => item.uuid}
          contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 8 }}
          onRefresh={() => refetch()}
          refreshing={isRefetching}
          ListEmptyComponent={
            <YStack height={320}>
              <EmptyState
                icon={<MessageCircle size={28} color={brand.primary} />}
                title="Aucun message"
                hint="Les conversations entamées par vos prospects apparaîtront ici."
              />
            </YStack>
          }
          renderItem={({ item }) => (
            <ConversationRow
              conversation={item}
              onPress={() => router.push(`/messages/${item.uuid}` as never)}
            />
          )}
        />
      )}
    </YStack>
  );
}

/** Preview inbox : corps du message, sinon libellé pièce jointe / scellé. */
function previewText(c: ConversationPreview): string {
  const last = c.last_message;
  if (!last) return 'Pas encore de message';
  if (last.is_client_sealed) return '🔐 Message sécurisé';
  if (last.body) return last.body;
  if (last.type === 'image') return '📷 Photo';
  return '📎 Pièce jointe';
}

function ConversationRow({
  conversation,
  onPress,
}: {
  conversation: ConversationPreview;
  onPress: () => void;
}) {
  const other = conversation.other_participant ?? null;
  const fullName = other?.name?.trim() || 'Conversation';
  const avatarUrl = resolveMediaUrl(other?.avatar);
  const isOnline = formatPresence(other?.last_seen_at).online;
  const isUnread = conversation.unread_count > 0;
  const timestamp = conversation.last_message?.created_at ?? conversation.last_message_at;
  let relative = '';
  try {
    relative = timestamp
      ? formatDistanceToNow(new Date(timestamp), { addSuffix: true, locale: fr })
      : '';
  } catch {
    relative = '';
  }

  return (
    <Pressable onPress={onPress} accessibilityRole="button">
      <XStack
        padding={12}
        gap={12}
        borderRadius={14}
        alignItems="center"
        backgroundColor={isUnread ? brand.primaryAlpha10 : '$background'}
        borderWidth={1}
        borderColor={isUnread ? brand.primaryAlpha20 : '$slate300'}
      >
        <YStack width={48} height={48}>
          <YStack
            width={48}
            height={48}
            borderRadius={24}
            overflow="hidden"
            backgroundColor={brand.primaryAlpha10}
            alignItems="center"
            justifyContent="center"
          >
            {avatarUrl ? (
              <Image
                source={{ uri: avatarUrl }}
                style={{ width: '100%', height: '100%' }}
                contentFit="cover"
                cachePolicy="memory-disk"
                recyclingKey={avatarUrl}
                transition={150}
              />
            ) : (
              <Paragraph fontSize={16} fontWeight="800" color={brand.primary}>
                {fullName.charAt(0).toUpperCase()}
              </Paragraph>
            )}
          </YStack>
          {/* Pastille « en ligne » façon Messenger, ancrée sur l'avatar. */}
          {isOnline && (
            <YStack
              position="absolute"
              bottom={0}
              right={0}
              width={13}
              height={13}
              borderRadius={7}
              backgroundColor="#22C55E"
              borderWidth={2}
              borderColor="$background"
            />
          )}
        </YStack>

        <YStack flex={1} gap={3}>
          <XStack alignItems="center" gap={6}>
            <Paragraph
              fontSize={14}
              fontWeight={isUnread ? '800' : '700'}
              color="$slate900"
              flex={1}
              numberOfLines={1}
            >
              {fullName}
            </Paragraph>
            <Paragraph fontSize={11} color="$slate500">
              {relative}
            </Paragraph>
          </XStack>
          {conversation.ad?.title ? (
            <Paragraph fontSize={12} color={brand.primary} fontWeight="600" numberOfLines={1}>
              {conversation.ad.title}
            </Paragraph>
          ) : null}
          <XStack alignItems="center" gap={6}>
            <Paragraph
              fontSize={12.5}
              color={isUnread ? '$slate900' : '$slate500'}
              flex={1}
              numberOfLines={1}
              fontWeight={isUnread ? '700' : '400'}
            >
              {previewText(conversation)}
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
                  {conversation.unread_count}
                </Paragraph>
              </YStack>
            ) : null}
          </XStack>
        </YStack>
      </XStack>
    </Pressable>
  );
}

/**
 * Skeleton de l'inbox : silhouettes de cartes conversation (avatar +
 * deux lignes de texte) — pas de spinner plein écran.
 */
function InboxSkeleton() {
  return (
    <YStack padding={16} gap={8}>
      {Array.from({ length: 6 }, (_, i) => (
        <XStack
          key={i}
          padding={12}
          gap={12}
          borderRadius={14}
          borderWidth={1}
          borderColor="$slate300"
          alignItems="center"
        >
          <Skeleton width={48} height={48} radius={24} />
          <YStack flex={1} gap={7}>
            <Skeleton width={`${52 + (i % 3) * 12}%`} height={13} radius={6} />
            <Skeleton width={`${70 - (i % 4) * 8}%`} height={10} radius={5} />
          </YStack>
        </XStack>
      ))}
    </YStack>
  );
}
