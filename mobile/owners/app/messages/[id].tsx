import { ImagePlus, Send, Trash2 } from '@tamagui/lucide-icons';
import { format, isToday, isYesterday } from 'date-fns';
import { fr } from 'date-fns/locale';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { useLocalSearchParams } from 'expo-router';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  Alert,
  Animated,
  Easing,
  FlatList,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  TextInput,
} from 'react-native';
import { useQueryClient } from '@tanstack/react-query';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useMe } from '@/hooks/useMe';
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
import { useReducedMotion } from '@/hooks/useReducedMotion';
import { useMotionPresets } from '@/hooks/useMotionPresets';
import { showToast } from '@/services/toast';
import { brand } from '@/theme/tokens';
import type { ConversationMessage, ConversationPreview } from '@/types/conversation';

const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

/** Fenêtre de regroupement des messages consécutifs d'un même expéditeur. */
const CLUSTER_GAP_MS = 5 * 60 * 1000;

/**
 * Normalise les réactions (déjà groupées par le backend :
 * { emoji, count, user_ids }) + indique si l'utilisateur courant a réagi.
 */
function groupReactions(
  reactions: ConversationMessage['reactions'],
  myId?: string,
): { emoji: string; count: number; mine: boolean }[] {
  if (!Array.isArray(reactions) || reactions.length === 0) return [];
  return reactions.map((r) => ({
    emoji: r.emoji,
    count: r.count ?? (Array.isArray(r.user_ids) ? r.user_ids.length : 0),
    mine: Array.isArray(r.user_ids) && myId != null && r.user_ids.includes(myId),
  }));
}

function TypingDots() {
  const reducedMotion = useReducedMotion();
  const dots = useRef([
    new Animated.Value(0.3),
    new Animated.Value(0.3),
    new Animated.Value(0.3),
  ]).current;

  useEffect(() => {
    // Reduced motion : trois points statiques — l'info « en train
    // d'écrire » reste lisible sans boucle d'oscillation.
    if (reducedMotion) {
      dots.forEach((d) => d.setValue(0.6));
      return;
    }

    const loop = Animated.loop(
      Animated.stagger(
        160,
        dots.map((d) =>
          Animated.sequence([
            Animated.timing(d, {
              toValue: 1,
              duration: 320,
              easing: Easing.inOut(Easing.ease),
              useNativeDriver: true,
            }),
            Animated.timing(d, {
              toValue: 0.3,
              duration: 320,
              easing: Easing.inOut(Easing.ease),
              useNativeDriver: true,
            }),
          ]),
        ),
      ),
    );
    loop.start();
    return () => loop.stop();
  }, [dots, reducedMotion]);

  return (
    <XStack gap={4} paddingHorizontal={12} paddingVertical={9} backgroundColor="$slate100" borderRadius={16} alignSelf="flex-start">
      {dots.map((d, i) => (
        <Animated.View
          key={i}
          style={{
            width: 6,
            height: 6,
            borderRadius: 3,
            backgroundColor: brand.slate500,
            opacity: d,
          }}
        />
      ))}
    </XStack>
  );
}

export default function ConversationThreadScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const me = useMe();
  const { scrollAnimated } = useMotionPresets();
  const qc = useQueryClient();
  const [draft, setDraft] = useState('');
  const listRef = useRef<FlatList<ConversationMessage> | null>(null);

  // Signature stable (currentUserId) — le hook gère l'état « typing » en
  // interne et ne se ré-abonne plus à chaque frappe.
  const realtime = useConversationRealtime(id, me.data?.id);
  const otherTyping = realtime.typingUser?.user_id ?? null;
  const { data: messages = [], isLoading } = useConversation(id, realtime.isConnected);
  const send = useSendMessage(id);
  const markRead = useMarkConversationRead(id);
  const setTyping = useSetTyping(id);
  const upload = useUploadAttachment(id);
  const toggleReaction = useToggleReaction(id);
  const deleteMessage = useDeleteMessage(id);
  const [reactionTarget, setReactionTarget] = useState<ConversationMessage | null>(null);

  // Préfetch depuis la cache des conversations (header info instant)
  const conversation = useMemo<ConversationPreview | undefined>(() => {
    const data = qc.getQueryData<{ data: ConversationPreview[] } | undefined>([
      'owner-conversations',
    ]);
    const list = Array.isArray(data?.data) ? data!.data : [];
    return list.find((c) => c.uuid === id);
  }, [qc, id]);

  // markRead au montage / changement de conversation uniquement — la
  // mutation est recréée à chaque render, on ne la met donc PAS en dep
  // (sinon boucle de PATCH /read). Ref pour appeler la version courante.
  const markReadRef = useRef(markRead);
  markReadRef.current = markRead;
  useEffect(() => {
    if (id) markReadRef.current.mutate();
  }, [id]);

  useEffect(() => {
    if (messages.length > 0) {
      listRef.current?.scrollToEnd({ animated: scrollAnimated });
    }
  }, [messages.length, scrollAnimated]);

  const onSubmit = () => {
    const body = draft.trim();
    if (!body) return;
    setDraft('');
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    send.mutate(body);
  };

  const handleRetry = (msg: ConversationMessage) => {
    if (!msg.is_failed || !msg.body) return;
    qc.setQueryData<{ data: ConversationMessage[] } | undefined>(
      ['owner-conversation-messages', id],
      (prev) => {
        if (!prev) return prev;
        const list = Array.isArray(prev.data) ? prev.data : [];
        return { data: list.filter((m) => m.uuid !== msg.uuid) };
      },
    );
    send.mutate(msg.body);
  };

  const handlePickImage = async () => {
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (perm.status !== 'granted') {
        Alert.alert('Permission requise', 'Autorisez l’accès aux photos.');
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
      showToast({ type: 'error', message: extractApiErrorMessage(err) });
    }
  };

  const otherName = conversation?.other_participant?.name?.trim() || 'Conversation';

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader
        title={otherName}
        subtitle={conversation?.ad?.title}
        right={
          conversation?.other_participant?.avatar ? (
            <YStack width={36} height={36} borderRadius={18} overflow="hidden" backgroundColor="$slate100">
              <Image
                source={{ uri: conversation.other_participant.avatar }}
                style={{ width: '100%', height: '100%' }}
                contentFit="cover"
              />
            </YStack>
          ) : (
            <YStack
              width={36}
              height={36}
              borderRadius={18}
              alignItems="center"
              justifyContent="center"
              backgroundColor={brand.primaryAlpha10}
            >
              <Paragraph fontSize={14} fontWeight="800" color={brand.primary}>
                {(conversation?.other_participant?.name?.[0] ?? '?').toUpperCase()}
              </Paragraph>
            </YStack>
          )
        }
      />

      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 80 : 0}
      >
        {isLoading ? (
          <YStack flex={1} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : (
          <FlatList
            ref={listRef}
            data={messages}
            keyExtractor={(item) => item.uuid}
            contentContainerStyle={{ paddingVertical: 14, paddingHorizontal: 12, gap: 4 }}
            ListEmptyComponent={
              <YStack padding={20} alignItems="center">
                <Paragraph color="$slate500">Écrivez le premier message.</Paragraph>
              </YStack>
            }
            ListFooterComponent={otherTyping ? <TypingDots /> : null}
            renderItem={({ item, index }) => {
              const mine = item.sender_id === me.data?.id || item.sender_id === '__me__';
              const prev = messages[index - 1];
              const next = messages[index + 1];
              const showDateSeparator = !prev || !sameDay(prev.created_at, item.created_at);
              const isTail =
                !next ||
                next.sender_id !== item.sender_id ||
                Math.abs(
                  new Date(next.created_at).getTime() - new Date(item.created_at).getTime(),
                ) >= CLUSTER_GAP_MS;

              return (
                <YStack gap={4}>
                  {showDateSeparator ? (
                    <Paragraph
                      fontSize={11}
                      color="$slate500"
                      textAlign="center"
                      marginTop={index === 0 ? 0 : 10}
                      marginBottom={6}
                    >
                      {formatDay(item.created_at)}
                    </Paragraph>
                  ) : null}
                  <MessageBubble
                    message={item}
                    mine={mine}
                    isTail={isTail}
                    myId={me.data?.id}
                    onLongPress={() => setReactionTarget(item)}
                    onRetry={() => handleRetry(item)}
                    onToggleReaction={(emoji, reacted) =>
                      toggleReaction.mutate({ messageId: item.uuid, emoji, reacted })
                    }
                  />
                </YStack>
              );
            }}
          />
        )}

        <XStack
          padding={10}
          gap={8}
          alignItems="center"
          borderTopWidth={0.5}
          borderTopColor="$slate300"
          backgroundColor="$background"
        >
          <Pressable
            onPress={handlePickImage}
            hitSlop={8}
            disabled={upload.isPending}
            accessibilityLabel="Envoyer une photo"
          >
            <YStack
              width={40}
              height={40}
              borderRadius={20}
              alignItems="center"
              justifyContent="center"
              backgroundColor={brand.slate100}
            >
              {upload.isPending ? (
                <Spinner size="small" color={brand.primary} />
              ) : (
                <ImagePlus size={20} color={brand.slate700} />
              )}
            </YStack>
          </Pressable>
          <TextInput
            value={draft}
            onChangeText={(v) => {
              setDraft(v);
              if (v.length > 0) setTyping.mutate();
            }}
            placeholder="Écrire un message…"
            placeholderTextColor={brand.slate500}
            multiline
            style={{
              flex: 1,
              maxHeight: 100,
              paddingHorizontal: 14,
              paddingVertical: 10,
              borderRadius: 20,
              backgroundColor: brand.slate100,
              color: brand.slate900,
              fontSize: 14,
            }}
          />
          <Pressable onPress={onSubmit} hitSlop={10} accessibilityLabel="Envoyer">
            <YStack
              width={42}
              height={42}
              borderRadius={21}
              alignItems="center"
              justifyContent="center"
              backgroundColor={draft.trim() ? brand.primary : brand.slate300}
            >
              <Send size={18} color="white" />
            </YStack>
          </Pressable>
        </XStack>
      </KeyboardAvoidingView>

      <Modal
        visible={reactionTarget !== null}
        transparent
        animationType="fade"
        onRequestClose={() => setReactionTarget(null)}
      >
        <Pressable style={{ flex: 1 }} onPress={() => setReactionTarget(null)}>
          <YStack flex={1} justifyContent="center" alignItems="center" backgroundColor="rgba(0,0,0,0.4)" gap={12}>
            <XStack
              backgroundColor="$background"
              borderRadius={999}
              padding={8}
              gap={4}
              onPress={(e) => e.stopPropagation()}
            >
              {REACTIONS.map((emoji) => (
                <Pressable
                  key={emoji}
                  onPress={() => {
                    const target = reactionTarget;
                    setReactionTarget(null);
                    if (!target) return;
                    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                    const mine = groupReactions(target.reactions, me.data?.id).some(
                      (g) => g.emoji === emoji && g.mine,
                    );
                    toggleReaction.mutate({ messageId: target.uuid, emoji, reacted: mine });
                  }}
                >
                  <YStack width={44} height={44} alignItems="center" justifyContent="center">
                    <Paragraph fontSize={26}>{emoji}</Paragraph>
                  </YStack>
                </Pressable>
              ))}
            </XStack>

            {/* Supprimer — uniquement mes propres messages déjà envoyés */}
            {reactionTarget &&
            (reactionTarget.sender_id === me.data?.id || reactionTarget.sender_id === '__me__') &&
            !reactionTarget.is_optimistic &&
            !reactionTarget.is_failed &&
            !reactionTarget.uuid.startsWith('temp:') ? (
              <Pressable
                onPress={(e) => {
                  e.stopPropagation();
                  const target = reactionTarget;
                  setReactionTarget(null);
                  if (!target) return;
                  Alert.alert(
                    'Supprimer le message',
                    'Ce message sera retiré de la conversation. Cette action est définitive.',
                    [
                      { text: 'Annuler', style: 'cancel' },
                      {
                        text: 'Supprimer',
                        style: 'destructive',
                        onPress: () => deleteMessage.mutate(target.uuid),
                      },
                    ],
                  );
                }}
              >
                <XStack
                  backgroundColor="$background"
                  borderRadius={14}
                  paddingHorizontal={20}
                  paddingVertical={12}
                  alignItems="center"
                  gap={8}
                >
                  <Trash2 size={18} color={brand.danger} />
                  <Paragraph fontSize={15} fontWeight="700" color={brand.danger}>
                    Supprimer
                  </Paragraph>
                </XStack>
              </Pressable>
            ) : null}
          </YStack>
        </Pressable>
      </Modal>
    </YStack>
  );
}

/**
 * Bulle de message façon Messenger : pièces jointes, texte (ou placeholder
 * scellé), réactions, et sur le message de fin de cluster (`isTail`) la
 * ligne méta (heure, envoi…/livré/lu, réessai) + avatar pour les reçus.
 */
function MessageBubble({
  message,
  mine,
  isTail,
  myId,
  onLongPress,
  onRetry,
  onToggleReaction,
}: {
  message: ConversationMessage;
  mine: boolean;
  isTail: boolean;
  myId?: string;
  onLongPress: () => void;
  onRetry: () => void;
  onToggleReaction: (emoji: string, reacted: boolean) => void;
}) {
  const time = (() => {
    try {
      return format(new Date(message.created_at), 'HH:mm', { locale: fr });
    } catch {
      return '';
    }
  })();

  const hasAttachments = Array.isArray(message.attachments) && message.attachments.length > 0;
  const grouped = groupReactions(message.reactions, myId);
  const isRead = Boolean(message.read_at);
  const isDelivered = Boolean(message.delivered_at) && !isRead;

  const bubble = (
    <YStack maxWidth="80%" alignItems={mine ? 'flex-end' : 'flex-start'} gap={3}>
      <Pressable onLongPress={onLongPress} delayLongPress={250}>
        <YStack
          paddingHorizontal={hasAttachments && !message.body ? 0 : 12}
          paddingVertical={hasAttachments && !message.body ? 0 : 9}
          borderRadius={18}
          backgroundColor={
            message.is_failed
              ? `${brand.danger}20`
              : mine
                ? brand.primary
                : '$slate100'
          }
          borderBottomRightRadius={mine && isTail ? 6 : 18}
          borderBottomLeftRadius={!mine && isTail ? 6 : 18}
          borderWidth={message.is_failed ? 1 : 0}
          borderColor={message.is_failed ? brand.danger : 'transparent'}
          opacity={message.is_optimistic ? 0.7 : 1}
          overflow="hidden"
        >
          {hasAttachments
            ? (message.attachments ?? []).map((att) => (
                <YStack
                  key={att.id ?? att.url}
                  width={220}
                  height={220}
                  borderRadius={12}
                  overflow="hidden"
                  backgroundColor="$slate200"
                  marginBottom={message.body ? 6 : 0}
                >
                  <Image
                    source={{ uri: att.url }}
                    style={{ width: '100%', height: '100%' }}
                    contentFit="cover"
                    transition={150}
                  />
                </YStack>
              ))
            : null}
          {message.body ? (
            <Paragraph
              fontSize={14.5}
              color={mine && !message.is_failed ? 'white' : '$slate900'}
              lineHeight={20}
            >
              {message.body}
            </Paragraph>
          ) : !hasAttachments && message.is_client_sealed ? (
            <Paragraph
              fontSize={13}
              fontStyle="italic"
              color={mine ? 'rgba(255,255,255,0.85)' : '$slate500'}
            >
              🔒 Message chiffré
            </Paragraph>
          ) : null}
        </YStack>
      </Pressable>

      {grouped.length > 0 ? (
        <XStack gap={4} flexWrap="wrap">
          {grouped.map((g) => (
            <Pressable key={g.emoji} onPress={() => onToggleReaction(g.emoji, g.mine)}>
              <XStack
                alignItems="center"
                gap={3}
                paddingHorizontal={7}
                paddingVertical={2}
                borderRadius={999}
                backgroundColor={g.mine ? brand.primaryAlpha10 : '$slate100'}
                borderWidth={g.mine ? 1 : 0}
                borderColor={g.mine ? brand.primary : 'transparent'}
              >
                <Paragraph fontSize={12}>{g.emoji}</Paragraph>
                {g.count > 1 ? (
                  <Paragraph fontSize={11} fontWeight="700" color="$slate700">
                    {g.count}
                  </Paragraph>
                ) : null}
              </XStack>
            </Pressable>
          ))}
        </XStack>
      ) : null}

      {isTail ? (
        <XStack alignItems="center" gap={4} marginTop={1}>
          {message.is_failed ? (
            <Pressable onPress={onRetry} hitSlop={4}>
              <Paragraph fontSize={10} fontWeight="700" color={brand.danger}>
                Échec — réessayer
              </Paragraph>
            </Pressable>
          ) : (
            <>
              <Paragraph fontSize={10} color="$slate500">
                {time}
              </Paragraph>
              {message.is_optimistic ? (
                <Paragraph fontSize={10} color="$slate500">
                  · envoi…
                </Paragraph>
              ) : null}
              {mine && isRead ? (
                <Paragraph fontSize={10} fontWeight="700" color={brand.primary}>
                  · lu
                </Paragraph>
              ) : mine && isDelivered ? (
                <Paragraph fontSize={10} color="$slate500">
                  · livré
                </Paragraph>
              ) : null}
            </>
          )}
        </XStack>
      ) : null}
    </YStack>
  );

  if (mine) {
    return (
      <XStack justifyContent="flex-end" paddingHorizontal={2}>
        {bubble}
      </XStack>
    );
  }

  // Message reçu : avatar aligné sur la bulle de fin de cluster (Messenger).
  return (
    <XStack justifyContent="flex-start" alignItems="flex-end" gap={6} paddingHorizontal={2}>
      {isTail ? (
        <MessageAvatar uri={message.sender?.avatar} name={message.sender?.name ?? '?'} />
      ) : (
        <YStack width={26} />
      )}
      {bubble}
    </XStack>
  );
}

/** Avatar rond compact : photo si dispo, sinon initiale sur fond neutre. */
function MessageAvatar({ uri, name }: { uri?: string | null; name: string }) {
  if (uri) {
    return (
      <YStack width={26} height={26} borderRadius={13} overflow="hidden" backgroundColor="$slate200">
        <Image source={{ uri }} style={{ width: '100%', height: '100%' }} contentFit="cover" />
      </YStack>
    );
  }
  return (
    <YStack
      width={26}
      height={26}
      borderRadius={13}
      alignItems="center"
      justifyContent="center"
      backgroundColor={brand.primaryAlpha10}
    >
      <Paragraph fontSize={11} fontWeight="800" color={brand.primary}>
        {(name[0] ?? '?').toUpperCase()}
      </Paragraph>
    </YStack>
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
