import { ArrowLeft, MessageCircle, RotateCw } from '@tamagui/lucide-icons';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Image } from 'expo-image';
import { Stack, useRouter } from 'expo-router';
import { FlatList, Pressable } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { Skeleton } from '@/components/Skeleton';
import { useConversations } from '@/hooks/useConversations';
import { resolveMediaUrl } from '@/lib/media-url';
import { formatPresence } from '@/lib/presence';
import { useSession } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';
import type { Conversation } from '@/types/conversation';

/**
 * Inbox — list of conversations with the most recent activity at the top.
 * Unread conversations get a coloured dot, the count, and a bold preview.
 * Tap any row to drop into the thread view.
 */
export default function MessagesInbox() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const { data, isLoading, isError, error, refetch, isRefetching } =
    useConversations();

  if (!isAuthenticated) {
    return <SignInWall onSignIn={() => router.push('/(auth)/login')} insets={insets} />;
  }

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
            <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
              <ArrowLeft size={18} color="$slate700" />
            </YStack>
          </Pressable>
          <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
            Messages
          </H2>
        </XStack>

        {isLoading ? (
          <InboxSkeleton />
        ) : isError ? (
          <YStack flex={1} justifyContent="center" alignItems="center" padding="$5" gap={12}>
            <Paragraph color="$slate700" textAlign="center">{extractApiErrorMessage(error)}</Paragraph>
            <Pressable onPress={() => refetch()} hitSlop={6} accessibilityRole="button" accessibilityLabel="Réessayer">
              <XStack alignItems="center" gap={6} paddingHorizontal={16} paddingVertical={10} borderRadius={999} backgroundColor="$slate900">
                <RotateCw size={14} color="white" />
                <Paragraph fontSize={13} fontWeight="700" color="white">Réessayer</Paragraph>
              </XStack>
            </Pressable>
          </YStack>
        ) : (
          <FlatList
            data={data ?? []}
            keyExtractor={(item) => item.uuid}
            contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
            onRefresh={() => refetch()}
            refreshing={isRefetching}
            ListEmptyComponent={
              <YStack padding="$6" alignItems="center" gap={8}>
                <MessageCircle size={36} color="$slate500" />
                <Paragraph fontSize={15} fontWeight="700" color="$slate900">
                  Pas encore de conversations
                </Paragraph>
                <Paragraph fontSize={13} color="$slate500" textAlign="center">
                  Contactez un bailleur depuis une annonce pour démarrer un échange.
                </Paragraph>
              </YStack>
            }
            ItemSeparatorComponent={() => (
              <YStack height={1} backgroundColor="$slate100" marginLeft={68} />
            )}
            renderItem={({ item }) => (
              <ConversationRow
                conversation={item}
                onPress={() => router.push(`/messages/${item.uuid}`)}
              />
            )}
          />
        )}
      </YStack>
    </>
  );
}

/** Preview inbox : corps du message, sinon libellé pièce jointe / scellé. */
function previewText(conversation: Conversation): string {
  const last = conversation.last_message;
  if (!last) return 'Aucun message';
  if (last.is_client_sealed) return '🔐 Message sécurisé';
  if (last.body) return last.body;
  if (last.type === 'image') return '📷 Photo';
  return '📎 Pièce jointe';
}

function ConversationRow({
  conversation,
  onPress,
}: {
  conversation: Conversation;
  onPress: () => void;
}) {
  const other = conversation.other_participant ?? null;
  const otherName = other?.name?.trim() || 'Conversation';
  const avatarUrl = resolveMediaUrl(other?.avatar);
  const isOnline = formatPresence(other?.last_seen_at).online;
  const lastMessage = conversation.last_message;
  const unread = (conversation.unread_count ?? 0) > 0;
  const timestamp = lastMessage?.created_at ?? conversation.last_message_at;
  let relative = '';
  try {
    relative = timestamp
      ? formatDistanceToNow(new Date(timestamp), { addSuffix: false, locale: fr })
      : '';
  } catch {
    relative = '';
  }

  return (
    <Pressable onPress={onPress} accessibilityRole="button">
      <XStack paddingVertical={14} paddingHorizontal={16} gap={12} alignItems="center">
        <YStack width={48} height={48}>
          <YStack
            width={48}
            height={48}
            borderRadius={24}
            backgroundColor={brand.primaryAlpha10}
            alignItems="center"
            justifyContent="center"
            overflow="hidden"
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
              <Paragraph fontSize={18} fontWeight="700" color={brand.primary}>
                {otherName.charAt(0).toUpperCase()}
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
          <XStack alignItems="center" justifyContent="space-between" gap={6}>
            <Paragraph
              fontSize={15}
              fontWeight={unread ? '800' : '600'}
              color="$slate900"
              numberOfLines={1}
              flex={1}
            >
              {otherName}
            </Paragraph>
            <Paragraph fontSize={11} color="$slate500">
              {relative}
            </Paragraph>
          </XStack>
          {conversation.ad?.title && (
            <Paragraph fontSize={12} color={brand.primary} fontWeight="600" numberOfLines={1}>
              {conversation.ad.title}
            </Paragraph>
          )}
          <XStack alignItems="center" gap={6}>
            <Paragraph
              fontSize={13}
              color={unread ? '$slate900' : '$slate500'}
              fontWeight={unread ? '600' : '400'}
              numberOfLines={1}
              flex={1}
            >
              {previewText(conversation)}
            </Paragraph>
            {unread && (
              <YStack
                minWidth={20}
                height={20}
                paddingHorizontal={6}
                borderRadius={10}
                backgroundColor={brand.primary}
                alignItems="center"
                justifyContent="center"
              >
                <Paragraph fontSize={10} fontWeight="800" color="white">
                  {conversation.unread_count}
                </Paragraph>
              </YStack>
            )}
          </XStack>
        </YStack>
      </XStack>
    </Pressable>
  );
}

function SignInWall({
  onSignIn,
  insets,
}: {
  onSignIn: () => void;
  insets: { top: number; bottom: number };
}) {
  return (
    <YStack
      flex={1}
      backgroundColor="$background"
      justifyContent="center"
      alignItems="center"
      gap={14}
      padding={28}
      paddingTop={insets.top + 28}
    >
      <MessageCircle size={40} color="$slate500" />
      <Paragraph fontSize={17} fontWeight="700" color="$slate900" textAlign="center">
        Connectez-vous pour voir vos messages
      </Paragraph>
      <Paragraph fontSize={13} color="$slate500" textAlign="center">
        Vos conversations avec les bailleurs apparaîtront ici.
      </Paragraph>
      <Pressable onPress={onSignIn} hitSlop={6}>
        <XStack
          backgroundColor="$brand"
          paddingHorizontal={20}
          paddingVertical={12}
          borderRadius={12}
        >
          <Paragraph color="white" fontWeight="700">
            Se connecter
          </Paragraph>
        </XStack>
      </Pressable>
    </YStack>
  );
}

/**
 * Skeleton de l'inbox : silhouettes de lignes de conversation (avatar +
 * deux lignes de texte) sous le header — pas de spinner plein écran.
 * Le cache persisté court-circuite cet état dès la deuxième ouverture.
 */
function InboxSkeleton() {
  return (
    <YStack paddingTop={6}>
      {Array.from({ length: 7 }, (_, i) => (
        <XStack key={i} paddingVertical={14} paddingHorizontal={16} gap={12} alignItems="center">
          <Skeleton width={48} height={48} radius={24} />
          <YStack flex={1} gap={7}>
            <Skeleton width={`${52 + (i % 3) * 12}%`} height={13} radius={6} />
            <Skeleton width={`${70 - (i % 4) * 8}%`} height={10} radius={5} />
          </YStack>
          <Skeleton width={34} height={9} radius={5} />
        </XStack>
      ))}
    </YStack>
  );
}
