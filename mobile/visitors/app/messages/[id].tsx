import { ArrowLeft, ImagePlus, RotateCw, Send, Smile, X } from '@tamagui/lucide-icons';
import { format, isToday, isYesterday } from 'date-fns';
import { fr } from 'date-fns/locale';
import * as ImagePicker from 'expo-image-picker';
import { Image } from 'expo-image';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  TextInput,
} from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import {
  useConversation,
  useDeleteMessage,
  useMarkConversationRead,
  useSendMessage,
  useSetTyping,
  useToggleReaction,
  useUploadAttachment,
} from '@/hooks/useConversations';
import { useConversationRealtime } from '@/hooks/useConversationRealtime';
import { useMe } from '@/hooks/useMe';
import { useSession } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';
import type { Message, MessageReaction } from '@/types/conversation';

const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
const CLUSTER_GAP_MS = 5 * 60 * 1000;
const TYPING_DEBOUNCE_MS = 1500;

/**
 * Messenger-style thread view.
 *  - **Clustering** : messages consécutifs du même expéditeur dans
 *    une fenêtre de 5 min sont regroupés (avatar / horodatage affichés
 *    seulement sur le dernier du cluster).
 *  - **Optimistic UI** : l'envoi insère immédiatement le message
 *    avec un voile gris + statut "envoi…", remplacé au retour serveur.
 *  - **Reactions** : long-press sur une bulle ouvre un picker emoji ;
 *    re-tap retire la réaction. Pondéré localement avant invalidate.
 *  - **Read receipts** : checkmark unique = livré, double check coloré
 *    = lu par le destinataire (`read_at`).
 *  - **Attachments** : bouton trombone → expo-image-picker → upload
 *    multipart. Pendant l'upload on affiche un overlay loading.
 *  - **Typing** : POST /typing rate-limité à chaque debounce 1.5 s
 *    pendant la saisie ; le backend diffuse aux autres participants.
 */
export default function ConversationScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const me = useMe();

  const { data, isLoading, isError, error } = useConversation(id);
  const realtime = useConversationRealtime(id, me.data?.id);
  const send = useSendMessage(id);
  const markRead = useMarkConversationRead(id);
  const typing = useSetTyping(id);
  const upload = useUploadAttachment(id);
  const toggleReaction = useToggleReaction(id);
  const deleteMessage = useDeleteMessage(id);

  const [text, setText] = useState('');
  const [reactionTarget, setReactionTarget] = useState<Message | null>(null);
  const listRef = useRef<FlatList<Message>>(null);
  const typingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Mark as read au mount + sur chaque fetch
  useEffect(() => {
    if (isAuthenticated && id) {
      markRead.mutate();
    }
    return () => {
      if (typingTimeoutRef.current) clearTimeout(typingTimeoutRef.current);
    };
  }, [id, isAuthenticated, markRead]);

  const messages = data ?? [];
  const myId = me.data?.id;

  // Inject local sender_id for optimistic temp messages so the bubble
  // renders on the right side immediately.
  const messagesResolved = useMemo(() => {
    if (!myId) return messages;
    return messages.map((m) =>
      m.sender_id === '__me__' ? { ...m, sender_id: myId } : m,
    );
  }, [messages, myId]);

  // Interlocuteur dérivé des messages reçus (nom + avatar) pour un
  // en-tête façon Messenger. Fallback neutre si aucun message reçu.
  const otherParticipant = useMemo(
    () => messages.find((m) => m.sender && m.sender_id !== myId)?.sender ?? null,
    [messages, myId],
  );

  useEffect(() => {
    if (messagesResolved.length > 0) {
      requestAnimationFrame(() => listRef.current?.scrollToEnd({ animated: true }));
    }
  }, [messagesResolved.length]);

  if (!isAuthenticated) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={10}>
        <Paragraph color="$slate700">Connectez-vous pour voir cette conversation.</Paragraph>
        <Pressable onPress={() => router.push('/(auth)/login')}>
          <XStack backgroundColor="$brand" paddingHorizontal={18} paddingVertical={10} borderRadius={10}>
            <Paragraph color="white" fontWeight="700">Se connecter</Paragraph>
          </XStack>
        </Pressable>
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
      <YStack flex={1} backgroundColor="$background" justifyContent="center" alignItems="center" padding="$5">
        <Paragraph color="$slate700" textAlign="center">{extractApiErrorMessage(error)}</Paragraph>
      </YStack>
    );
  }

  const handleSend = async () => {
    const body = text.trim();
    if (!body || send.isPending) return;
    setText('');
    try {
      await send.mutateAsync(body);
    } catch {
      setText(body);
    }
  };

  const handleRetry = async (msg: Message) => {
    if (!msg.is_failed || !msg.body) return;
    try {
      await send.mutateAsync(msg.body);
    } catch {
      /* l'état failed reste */
    }
  };

  const handleTextChange = (v: string) => {
    setText(v);
    // debounced typing ping
    if (!typingTimeoutRef.current) {
      typing.mutate();
      typingTimeoutRef.current = setTimeout(() => {
        typingTimeoutRef.current = null;
      }, TYPING_DEBOUNCE_MS);
    }
  };

  const handlePickImage = async () => {
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (perm.status !== 'granted') {
        Alert.alert('Permission requise', 'Autorisez l\'accès aux photos.');
        return;
      }
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        quality: 0.8,
      });
      if (result.canceled || !result.assets[0]) return;
      const asset = result.assets[0];
      await upload.mutateAsync({
        uri: asset.uri,
        name: asset.fileName ?? asset.uri.split('/').pop() ?? 'photo.jpg',
        type: asset.mimeType ?? 'image/jpeg',
      });
    } catch (err) {
      Alert.alert('Erreur', extractApiErrorMessage(err));
    }
  };

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
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
            <Pressable
              onPress={() => router.back()}
              hitSlop={8}
              accessibilityRole="button"
              accessibilityLabel="Retour à mes conversations"
            >
              <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
                <ArrowLeft size={18} color="$slate700" />
              </YStack>
            </Pressable>
            <Avatar
              uri={otherParticipant?.avatar}
              name={otherParticipant?.name ?? 'Conversation'}
              size={38}
            />
            <YStack flex={1} gap={1}>
              <Paragraph fontSize={16} fontWeight="700" color="$slate900" numberOfLines={1}>
                {otherParticipant?.name ?? 'Conversation'}
              </Paragraph>
              {realtime.typingUser?.is_typing ? (
                <Paragraph fontSize={11} color={brand.primary} fontWeight="600">
                  En train d'écrire…
                </Paragraph>
              ) : realtime.isConnected ? (
                <Paragraph fontSize={11} color="$slate500">
                  En direct
                </Paragraph>
              ) : null}
            </YStack>
          </XStack>

          <FlatList
            ref={listRef}
            data={messagesResolved}
            keyExtractor={(item) => item.uuid}
            contentContainerStyle={{
              paddingVertical: 14,
              paddingHorizontal: 12,
              gap: 4,
            }}
            ListEmptyComponent={
              <YStack padding="$5" alignItems="center" gap={4}>
                <Paragraph color="$slate500">Soyez le premier à écrire.</Paragraph>
              </YStack>
            }
            renderItem={({ item, index }) => {
              const isMine = item.sender_id === myId;
              const prev = messagesResolved[index - 1];
              const next = messagesResolved[index + 1];

              const showDateSeparator =
                !prev || !sameDay(prev.created_at, item.created_at);

              const sameSenderAsNext =
                next &&
                next.sender_id === item.sender_id &&
                Math.abs(
                  new Date(next.created_at).getTime() -
                    new Date(item.created_at).getTime(),
                ) < CLUSTER_GAP_MS;

              return (
                <YStack gap={4}>
                  {showDateSeparator && (
                    <Paragraph
                      fontSize={11}
                      color="$slate500"
                      textAlign="center"
                      marginTop={index === 0 ? 0 : 10}
                      marginBottom={6}
                    >
                      {formatDay(item.created_at)}
                    </Paragraph>
                  )}
                  <MessageBubble
                    message={item}
                    isMine={isMine}
                    isTail={!sameSenderAsNext}
                    onLongPress={() => setReactionTarget(item)}
                    onRetry={() => handleRetry(item)}
                  />
                </YStack>
              );
            }}
          />

          {upload.isPending && (
            <YStack
              paddingVertical={6}
              alignItems="center"
              backgroundColor={brand.primaryAlpha10}
            >
              <XStack alignItems="center" gap={6}>
                <ActivityIndicator size="small" color={brand.primary} />
                <Paragraph fontSize={11} fontWeight="700" color={brand.primary}>
                  Envoi de la photo…
                </Paragraph>
              </XStack>
            </YStack>
          )}

          <XStack
            paddingHorizontal={10}
            paddingVertical={8}
            paddingBottom={insets.bottom + 8}
            gap={6}
            alignItems="flex-end"
            borderTopWidth={1}
            borderTopColor="$slate300"
            backgroundColor="$background"
          >
            <Pressable onPress={handlePickImage} disabled={upload.isPending} hitSlop={4}>
              <YStack
                width={38}
                height={38}
                borderRadius={19}
                backgroundColor="$slate100"
                alignItems="center"
                justifyContent="center"
              >
                <ImagePlus size={18} color="$slate700" />
              </YStack>
            </Pressable>
            <YStack
              flex={1}
              borderWidth={1}
              borderColor="$slate300"
              borderRadius={20}
              paddingHorizontal={14}
              paddingVertical={8}
              backgroundColor="$slate100"
            >
              <TextInput
                value={text}
                onChangeText={handleTextChange}
                placeholder="Écrire un message…"
                placeholderTextColor={brand.slate500}
                multiline
                style={{
                  fontSize: 15,
                  color: brand.slate900,
                  maxHeight: 120,
                  paddingTop: 4,
                  paddingBottom: 4,
                }}
              />
            </YStack>
            <Pressable
              onPress={handleSend}
              disabled={text.trim().length === 0 || send.isPending}
              hitSlop={4}
            >
              <YStack
                width={42}
                height={42}
                borderRadius={21}
                backgroundColor={text.trim().length === 0 ? brand.slate300 : brand.primary}
                alignItems="center"
                justifyContent="center"
              >
                {send.isPending ? (
                  <ActivityIndicator color="white" size="small" />
                ) : (
                  <Send size={18} color="white" />
                )}
              </YStack>
            </Pressable>
          </XStack>

          {/* Reaction picker */}
          <Modal
            visible={reactionTarget != null}
            transparent
            animationType="fade"
            onRequestClose={() => setReactionTarget(null)}
          >
            <Pressable
              style={{ flex: 1, backgroundColor: 'rgba(0,0,0,0.4)', justifyContent: 'center', alignItems: 'center' }}
              onPress={() => setReactionTarget(null)}
            >
              <YStack
                backgroundColor="$background"
                borderRadius={999}
                paddingHorizontal={10}
                paddingVertical={8}
                shadowColor="rgba(0,0,0,0.25)"
                shadowOffset={{ width: 0, height: 4 }}
                shadowOpacity={1}
                shadowRadius={12}
              >
                <XStack gap={6}>
                  {REACTIONS.map((emoji) => {
                    const reacted =
                      reactionTarget?.reactions?.some(
                        (r) => r.emoji === emoji && r.reacted_by_me,
                      ) ?? false;
                    return (
                      <Pressable
                        key={emoji}
                        onPress={() => {
                          if (!reactionTarget) return;
                          toggleReaction.mutate({
                            messageUuid: reactionTarget.uuid,
                            emoji,
                            reacted,
                          });
                          setReactionTarget(null);
                        }}
                        hitSlop={4}
                      >
                        <YStack
                          width={44}
                          height={44}
                          borderRadius={22}
                          backgroundColor={reacted ? brand.primaryAlpha10 : 'transparent'}
                          alignItems="center"
                          justifyContent="center"
                        >
                          <Paragraph fontSize={24}>{emoji}</Paragraph>
                        </YStack>
                      </Pressable>
                    );
                  })}
                </XStack>
                {reactionTarget?.sender_id === myId && (
                  <Pressable
                    onPress={() => {
                      if (!reactionTarget) return;
                      Alert.alert(
                        'Supprimer ce message ?',
                        '',
                        [
                          { text: 'Annuler', style: 'cancel' },
                          {
                            text: 'Supprimer',
                            style: 'destructive',
                            onPress: () => {
                              deleteMessage.mutate(reactionTarget.uuid);
                              setReactionTarget(null);
                            },
                          },
                        ],
                      );
                    }}
                  >
                    <XStack alignItems="center" justifyContent="center" gap={6} paddingTop={8} paddingBottom={4}>
                      <X size={13} color={brand.danger} />
                      <Paragraph fontSize={12} fontWeight="700" color={brand.danger}>
                        Supprimer
                      </Paragraph>
                    </XStack>
                  </Pressable>
                )}
              </YStack>
            </Pressable>
          </Modal>
        </YStack>
      </KeyboardAvoidingView>
    </>
  );
}

/**
 * Bulle Messenger-style :
 *  - Tail (queue arrondie) seulement sur le dernier message du cluster
 *  - Horodatage + check de lecture seulement sur le tail
 *  - Attachments (Image) rendus en pré-bulle
 *  - Réactions affichées sous la bulle si > 0
 *  - Long-press → onLongPress (parent ouvre le picker)
 *  - is_optimistic → bulle plus claire
 *  - is_failed → bordure rouge + bouton retry
 */
function MessageBubble({
  message,
  isMine,
  isTail,
  onLongPress,
  onRetry,
}: {
  message: Message;
  isMine: boolean;
  isTail: boolean;
  onLongPress: () => void;
  onRetry: () => void;
}) {
  const time = useMemo(() => {
    try {
      return format(new Date(message.created_at), 'HH:mm', { locale: fr });
    } catch {
      return '';
    }
  }, [message.created_at]);

  const hasAttachments = Array.isArray(message.attachments) && message.attachments.length > 0;
  const hasReactions = Array.isArray(message.reactions) && message.reactions.length > 0;
  const isDelivered = !!message.delivered_at && !message.read_at;
  const isRead = !!message.read_at;
  // Message E2EE legacy non déchiffrable ici : on affiche un placeholder
  // plutôt qu'une bulle vide (l'E2EE est désactivé pour les nouveaux msg).
  const isSealed = Boolean(message.is_client_sealed) && !message.body;
  const hasText = (message.body?.length ?? 0) > 0;

  const bubble = (
    <YStack alignSelf={isMine ? 'flex-end' : 'flex-start'} maxWidth="80%">
      <Pressable onLongPress={onLongPress} delayLongPress={250}>
        <YStack gap={4}>
          {hasAttachments &&
            (message.attachments ?? []).map((att) => (
              <YStack
                key={att.url}
                width={220}
                height={220}
                borderRadius={16}
                overflow="hidden"
                backgroundColor="$slate100"
              >
                {att.mime_type.startsWith('image/') ? (
                  <Image
                    source={{ uri: att.url }}
                    style={{ width: '100%', height: '100%' }}
                    contentFit="cover"
                    transition={200}
                  />
                ) : (
                  <YStack
                    flex={1}
                    alignItems="center"
                    justifyContent="center"
                    padding={20}
                  >
                    <Paragraph fontSize={12} color="$slate500" numberOfLines={2}>
                      {att.name ?? 'Fichier'}
                    </Paragraph>
                  </YStack>
                )}
              </YStack>
            ))}
          {hasText && (
            <YStack
              paddingHorizontal={14}
              paddingVertical={9}
              borderRadius={18}
              backgroundColor={
                message.is_failed
                  ? `${brand.danger}20`
                  : isMine
                    ? brand.primary
                    : brand.slate100
              }
              borderBottomRightRadius={isMine && isTail ? 6 : 18}
              borderBottomLeftRadius={!isMine && isTail ? 6 : 18}
              borderWidth={message.is_failed ? 1 : 0}
              borderColor={message.is_failed ? brand.danger : 'transparent'}
              opacity={message.is_optimistic ? 0.7 : 1}
            >
              <Paragraph
                fontSize={14.5}
                color={isMine && !message.is_failed ? 'white' : '$slate900'}
                lineHeight={20}
              >
                {message.body}
              </Paragraph>
            </YStack>
          )}
          {isSealed && !hasAttachments && (
            <XStack
              paddingHorizontal={14}
              paddingVertical={9}
              borderRadius={18}
              backgroundColor={brand.slate100}
              borderBottomLeftRadius={!isMine && isTail ? 6 : 18}
              borderBottomRightRadius={isMine && isTail ? 6 : 18}
              alignItems="center"
              gap={6}
            >
              <Paragraph fontSize={13}>🔒</Paragraph>
              <Paragraph fontSize={13} color="$slate500" fontStyle="italic">
                Message chiffré
              </Paragraph>
            </XStack>
          )}
        </YStack>
      </Pressable>

      {hasReactions && (
        <XStack
          marginTop={-6}
          alignSelf={isMine ? 'flex-end' : 'flex-start'}
          paddingHorizontal={6}
          paddingVertical={2}
          borderRadius={999}
          backgroundColor="$background"
          shadowColor="rgba(0,0,0,0.12)"
          shadowOffset={{ width: 0, height: 1 }}
          shadowOpacity={1}
          shadowRadius={2}
          borderWidth={0.5}
          borderColor="$slate300"
          gap={2}
        >
          {(message.reactions ?? []).map((r) => (
            <ReactionPill key={r.emoji} reaction={r} />
          ))}
        </XStack>
      )}

      {isTail && (
        <XStack
          alignSelf={isMine ? 'flex-end' : 'flex-start'}
          marginTop={2}
          alignItems="center"
          gap={4}
        >
          {message.is_failed ? (
            <Pressable onPress={onRetry} hitSlop={4}>
              <XStack alignItems="center" gap={3}>
                <RotateCw size={11} color={brand.danger} />
                <Paragraph fontSize={10} fontWeight="700" color={brand.danger}>
                  Échec — réessayer
                </Paragraph>
              </XStack>
            </Pressable>
          ) : (
            <>
              <Paragraph fontSize={10} color="$slate500">{time}</Paragraph>
              {message.is_optimistic && (
                <Paragraph fontSize={10} color="$slate500">· envoi…</Paragraph>
              )}
              {isMine && isRead && (
                <Paragraph fontSize={10} color={brand.info} fontWeight="700">· lu</Paragraph>
              )}
              {isMine && isDelivered && !isRead && (
                <Paragraph fontSize={10} color="$slate500">· livré</Paragraph>
              )}
            </>
          )}
        </XStack>
      )}
    </YStack>
  );

  // Messages reçus : petit avatar aligné sur la bulle de fin de cluster
  // (façon Messenger). Les messages groupés gardent un décalage pour
  // rester alignés. Les messages envoyés n'ont pas d'avatar.
  if (isMine) {
    return bubble;
  }

  return (
    <XStack alignItems="flex-end" gap={6} maxWidth="86%">
      {isTail ? (
        <Avatar uri={message.sender?.avatar} name={message.sender?.name ?? '?'} size={26} />
      ) : (
        <YStack width={26} />
      )}
      {bubble}
    </XStack>
  );
}

/** Avatar rond : photo si dispo, sinon initiales sur fond neutre. */
function Avatar({
  uri,
  name,
  size,
}: {
  uri?: string | null;
  name: string;
  size: number;
}) {
  const initials = name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('');

  return (
    <YStack
      width={size}
      height={size}
      borderRadius={size / 2}
      backgroundColor="$slate200"
      alignItems="center"
      justifyContent="center"
      overflow="hidden"
    >
      {uri ? (
        <Image
          source={{ uri }}
          style={{ width: size, height: size }}
          contentFit="cover"
          transition={150}
        />
      ) : (
        <Paragraph fontSize={size * 0.4} fontWeight="800" color="$slate600">
          {initials || '?'}
        </Paragraph>
      )}
    </YStack>
  );
}

function ReactionPill({ reaction }: { reaction: MessageReaction }) {
  return (
    <XStack alignItems="center" gap={2} paddingHorizontal={4}>
      <Paragraph fontSize={12}>{reaction.emoji}</Paragraph>
      {reaction.count > 1 && (
        <Paragraph fontSize={10} fontWeight="700" color="$slate700">
          {reaction.count}
        </Paragraph>
      )}
    </XStack>
  );
}

function sameDay(a: string, b: string): boolean {
  try {
    const da = new Date(a);
    const db = new Date(b);
    return (
      da.getFullYear() === db.getFullYear() &&
      da.getMonth() === db.getMonth() &&
      da.getDate() === db.getDate()
    );
  } catch {
    return false;
  }
}

function formatDay(iso: string): string {
  try {
    const date = new Date(iso);
    if (isToday(date)) return "Aujourd'hui";
    if (isYesterday(date)) return 'Hier';
    return format(date, 'd MMMM', { locale: fr });
  } catch {
    return '';
  }
}
