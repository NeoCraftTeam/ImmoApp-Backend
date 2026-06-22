import { Send } from '@tamagui/lucide-icons';
import { useLocalSearchParams } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  TextInput,
} from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { ScreenHeader } from '@/components/ScreenHeader';
import { useMe } from '@/hooks/useMe';
import {
  useConversation,
  useMarkConversationRead,
  useSendMessage,
  useSetTyping,
} from '@/hooks/useConversations';
import { useConversationRealtime } from '@/hooks/useConversationRealtime';
import { brand } from '@/theme/tokens';

export default function ConversationThreadScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const me = useMe();
  const { data: messages = [], isLoading } = useConversation(id);
  const send = useSendMessage(id);
  const markRead = useMarkConversationRead(id);
  const setTyping = useSetTyping(id);

  const [draft, setDraft] = useState('');
  const [otherTyping, setOtherTyping] = useState<string | null>(null);
  const scrollRef = useRef<ScrollView | null>(null);

  useConversationRealtime(id, (uid) => {
    if (!me.data || uid === me.data.id) return;
    setOtherTyping(uid);
    setTimeout(() => setOtherTyping(null), 3000);
  });

  useEffect(() => {
    if (id) {
      markRead.mutate();
    }
    // mark-read effect intentionnel : à chaque ouverture du thread
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

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Conversation" />

      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 80 : 0}
      >
        <ScrollView
          ref={scrollRef}
          contentContainerStyle={{ padding: 14, paddingBottom: 20, gap: 10 }}
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
                  key={m.id}
                  justifyContent={mine ? 'flex-end' : 'flex-start'}
                  paddingHorizontal={2}
                >
                  <YStack
                    maxWidth="78%"
                    paddingHorizontal={12}
                    paddingVertical={9}
                    borderRadius={16}
                    backgroundColor={mine ? brand.primary : '$slate100'}
                  >
                    <Paragraph fontSize={14} color={mine ? 'white' : '$slate900'}>
                      {m.body}
                    </Paragraph>
                    {m.created_at ? (
                      <Paragraph fontSize={10} color={mine ? 'rgba(255,255,255,0.7)' : '$slate500'} textAlign="right">
                        {new Date(m.created_at).toLocaleTimeString('fr-FR', {
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </Paragraph>
                    ) : null}
                  </YStack>
                </XStack>
              );
            })
          )}
          {otherTyping ? (
            <Paragraph fontSize={11.5} color="$slate500" fontStyle="italic">
              En train d'écrire…
            </Paragraph>
          ) : null}
        </ScrollView>

        <XStack
          padding={10}
          gap={8}
          alignItems="center"
          borderTopWidth={0.5}
          borderTopColor="$slate300"
          backgroundColor="$background"
        >
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
          <Pressable onPress={onSubmit} hitSlop={10}>
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
