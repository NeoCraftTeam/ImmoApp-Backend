import { Check, CheckCheck, ImagePlus, Send } from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { useLocalSearchParams } from 'expo-router';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  Alert,
  Animated,
  Easing,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  TextInput,
} from 'react-native';
import { useQueryClient } from '@tanstack/react-query';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useMe } from '@/hooks/useMe';
import {
  useConversation,
  useMarkConversationRead,
  useSendMessage,
  useSetTyping,
  useUploadAttachment,
} from '@/hooks/useConversations';
import { useConversationRealtime } from '@/hooks/useConversationRealtime';
import { brand } from '@/theme/tokens';
import type { ConversationPreview } from '@/types/conversation';

function TypingDots() {
  const dots = useRef([
    new Animated.Value(0.3),
    new Animated.Value(0.3),
    new Animated.Value(0.3),
  ]).current;

  useEffect(() => {
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
  }, [dots]);

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
  const qc = useQueryClient();
  const { data: messages = [], isLoading } = useConversation(id);
  const send = useSendMessage(id);
  const markRead = useMarkConversationRead(id);
  const setTyping = useSetTyping(id);
  const upload = useUploadAttachment(id);

  // Préfetch depuis la cache des conversations (header info instant)
  const conversation = useMemo<ConversationPreview | undefined>(() => {
    const data = qc.getQueryData<{ data: ConversationPreview[] } | undefined>([
      'owner-conversations',
    ]);
    const list = Array.isArray(data?.data) ? data!.data : [];
    return list.find((c) => c.uuid === id);
  }, [qc, id]);

  const [draft, setDraft] = useState('');
  const [otherTyping, setOtherTyping] = useState<string | null>(null);
  const scrollRef = useRef<ScrollView | null>(null);
  const typingTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useConversationRealtime(id, (uid) => {
    if (!me.data || uid === me.data.id) return;
    setOtherTyping(uid);
    // Cleanup le timer precedent avant d'en starter un nouveau —
    // sans ce clear, 5 messages "typing" rapproches stackent 5 timers
    // qui fire tous a 3 s, polluant le state apres le user a tape.
    // Pire : si l'utilisateur navigue ailleurs, ces timers leak et
    // continuent a appeler setOtherTyping sur un component demonted
    // (warning RN + memoire qui s'accumule).
    if (typingTimerRef.current) clearTimeout(typingTimerRef.current);
    typingTimerRef.current = setTimeout(() => {
      setOtherTyping(null);
      typingTimerRef.current = null;
    }, 3000);
  });

  // Cleanup au unmount : pas de leak du timer si user navigue
  // pendant que le ping "typing" est encore valide.
  useEffect(() => {
    return () => {
      if (typingTimerRef.current) clearTimeout(typingTimerRef.current);
    };
  }, []);

  useEffect(() => {
    if (id) markRead.mutate();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  useEffect(() => {
    scrollRef.current?.scrollToEnd({ animated: true });
  }, [messages.length]);

  const onSubmit = () => {
    const body = draft.trim();
    if (!body) return;
    setDraft('');
    send.mutate(body);
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
      Alert.alert('Erreur', extractApiErrorMessage(err));
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
        <ScrollView
          ref={scrollRef}
          contentContainerStyle={{ padding: 14, paddingBottom: 20, gap: 8 }}
          onContentSizeChange={() => scrollRef.current?.scrollToEnd({ animated: false })}
        >
          {isLoading ? (
            <YStack height={200} alignItems="center" justifyContent="center">
              <Spinner color={brand.primary} size="large" />
            </YStack>
          ) : (
            messages.map((m) => {
              const mine = m.sender_id === me.data?.id || m.sender_id === '__me__';
              return (
                <XStack
                  key={m.uuid}
                  justifyContent={mine ? 'flex-end' : 'flex-start'}
                  paddingHorizontal={2}
                >
                  <YStack
                    maxWidth="78%"
                    paddingHorizontal={12}
                    paddingVertical={9}
                    borderRadius={16}
                    backgroundColor={mine ? brand.primary : '$slate100'}
                    borderBottomRightRadius={mine ? 4 : 16}
                    borderBottomLeftRadius={mine ? 16 : 4}
                  >
                    {Array.isArray(m.attachments) && m.attachments.length > 0
                      ? m.attachments.map((att) => (
                          <YStack
                            key={att.id ?? att.url}
                            width={200}
                            height={200}
                            borderRadius={12}
                            overflow="hidden"
                            backgroundColor="$slate200"
                            marginBottom={m.body ? 6 : 0}
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
                    {m.body ? (
                      <Paragraph fontSize={14} color={mine ? 'white' : '$slate900'} lineHeight={19}>
                        {m.body}
                      </Paragraph>
                    ) : m.is_client_sealed ? (
                      <Paragraph
                        fontSize={13}
                        fontStyle="italic"
                        color={mine ? 'rgba(255,255,255,0.85)' : '$slate500'}
                      >
                        🔒 Message chiffré
                      </Paragraph>
                    ) : null}
                    <XStack alignItems="center" gap={4} justifyContent="flex-end" marginTop={2}>
                      {m.created_at ? (
                        <Paragraph
                          fontSize={10}
                          color={mine ? 'rgba(255,255,255,0.75)' : '$slate500'}
                        >
                          {new Date(m.created_at).toLocaleTimeString('fr-FR', {
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </Paragraph>
                      ) : null}
                      {mine ? (
                        m.read_at ? (
                          <CheckCheck size={12} color="rgba(255,255,255,0.85)" />
                        ) : (
                          <Check size={12} color="rgba(255,255,255,0.65)" />
                        )
                      ) : null}
                    </XStack>
                  </YStack>
                </XStack>
              );
            })
          )}
          {otherTyping ? <TypingDots /> : null}
        </ScrollView>

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
    </YStack>
  );
}
