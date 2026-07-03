import { ArrowLeft, FileText, Paperclip, Send } from '@tamagui/lucide-icons';
import { format, formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import * as ImagePicker from 'expo-image-picker';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Alert, KeyboardAvoidingView, Platform, Pressable, ScrollView, TextInput } from 'react-native';
import { Image } from 'expo-image';
import { Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useDispute, useSendDisputeMessage, useUploadDisputeEvidence } from '@/hooks/useDisputes';
import { useMe } from '@/hooks/useMe';
import { brand } from '@/theme/tokens';
import type { DisputeStatus } from '@/types/dispute';

const STATUS_STEPS: { key: DisputeStatus; label: string }[] = [
  { key: 'open', label: 'Ouvert' },
  { key: 'review', label: 'Examen' },
  { key: 'mediation', label: 'Médiation' },
  { key: 'resolved', label: 'Résolu' },
];

/**
 * Détail d'un litige. Stepper du statut + métadonnées + messagerie
 * intégrée. Polling 30s pour rester en phase avec un opérateur qui
 * change le statut côté admin.
 */
export default function DisputeDetail() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { id } = useLocalSearchParams<{ id: string }>();
  const me = useMe();
  const { data, isLoading, isError, error } = useDispute(id);
  const send = useSendDisputeMessage(id);
  const upload = useUploadDisputeEvidence(id);
  const [text, setText] = useState('');

  const handlePickEvidence = async () => {
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (perm.status !== 'granted') {
        Alert.alert('Permission requise', 'Autorisez l\'accès aux photos pour joindre une preuve.');
        return;
      }
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.All,
        quality: 0.8,
      });
      if (result.canceled || !result.assets[0]) return;
      const asset = result.assets[0];
      const filename = asset.fileName ?? asset.uri.split('/').pop() ?? 'preuve.jpg';
      const mime = asset.mimeType ?? (asset.type === 'video' ? 'video/mp4' : 'image/jpeg');
      const type: 'photo' | 'document' = asset.type === 'video' ? 'document' : 'photo';
      await upload.mutateAsync({
        file: { uri: asset.uri, name: filename, type: mime },
        type,
      });
      Alert.alert('Preuve ajoutée', 'Le fichier a été envoyé au litige.');
    } catch (err) {
      Alert.alert('Erreur', extractApiErrorMessage(err));
    }
  };

  const currentStep = data ? STATUS_STEPS.findIndex((s) => s.key === data.status) : -1;

  const handleSend = async () => {
    const body = text.trim();
    if (!body) return;
    setText('');
    try {
      await send.mutateAsync(body);
    } catch (err) {
      setText(body);
      Alert.alert('Erreur', extractApiErrorMessage(err));
    }
  };

  if (isLoading) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center">
        <ActivityIndicator />
      </YStack>
    );
  }
  if (isError || !data) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5">
        <Paragraph color="$slate700">{extractApiErrorMessage(error)}</Paragraph>
      </YStack>
    );
  }

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
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
            <Paragraph fontSize={16} fontWeight="700" color="$slate900" flex={1} numberOfLines={1}>
              Litige {data.reference ?? `#${data.id.slice(0, 6)}`}
            </Paragraph>
          </XStack>

          <ScrollView
            contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 60 }}
          >
            <Paragraph fontSize={17} fontWeight="700" color="$slate900">{data.subject}</Paragraph>

            {/* Status stepper */}
            <YStack gap={10}>
              <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">
                Statut
              </Paragraph>
              <XStack alignItems="center" gap={4}>
                {STATUS_STEPS.map((step, idx) => (
                  <XStack key={step.key} flex={1} alignItems="center" gap={4}>
                    <YStack
                      width={22}
                      height={22}
                      borderRadius={11}
                      backgroundColor={idx <= currentStep ? brand.primary : brand.slate300}
                      alignItems="center"
                      justifyContent="center"
                    >
                      <Paragraph fontSize={11} fontWeight="800" color="white">
                        {idx + 1}
                      </Paragraph>
                    </YStack>
                    {idx < STATUS_STEPS.length - 1 && (
                      <YStack flex={1} height={2} backgroundColor={idx < currentStep ? brand.primary : brand.slate300} />
                    )}
                  </XStack>
                ))}
              </XStack>
              <Paragraph fontSize={13} fontWeight="700" color={brand.primary}>
                {STATUS_STEPS[currentStep]?.label ?? data.status}
              </Paragraph>
            </YStack>

            {data.description && (
              <YStack gap={4}>
                <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">Description</Paragraph>
                <Paragraph fontSize={14} color="$slate700" lineHeight={20}>{data.description}</Paragraph>
              </YStack>
            )}

            {data.amount != null && (
              <Paragraph fontSize={13} color="$slate500">
                Montant : <Paragraph fontWeight="700" color="$slate900">{data.amount.toLocaleString('fr-FR')} {data.currency ?? 'XAF'}</Paragraph>
              </Paragraph>
            )}

            <YStack gap={8}>
              <XStack alignItems="center" justifyContent="space-between">
                <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">
                  Preuves
                </Paragraph>
                <Pressable onPress={handlePickEvidence} disabled={upload.isPending} hitSlop={6}>
                  <XStack alignItems="center" gap={4} paddingHorizontal={10} paddingVertical={5} borderRadius={999} backgroundColor={brand.primaryAlpha10}>
                    {upload.isPending ? <ActivityIndicator size="small" /> : <Paperclip size={13} color={brand.primary} />}
                    <Paragraph fontSize={12} fontWeight="700" color={brand.primary}>
                      {upload.isPending ? 'Envoi…' : 'Ajouter'}
                    </Paragraph>
                  </XStack>
                </Pressable>
              </XStack>
              {(data.evidences ?? []).length === 0 ? (
                <Paragraph fontSize={13} color="$slate500">Aucune preuve pour l'instant.</Paragraph>
              ) : (
                <XStack flexWrap="wrap" gap={8}>
                  {(data.evidences ?? []).map((ev) => (
                    <YStack key={ev.id} width={80} height={80} borderRadius={10} backgroundColor="$slate100" overflow="hidden" alignItems="center" justifyContent="center">
                      {ev.type === 'photo' || ev.type === 'screenshot' ? (
                        <Image source={{ uri: ev.url }} style={{ width: '100%', height: '100%' }} contentFit="cover" />
                      ) : (
                        <YStack alignItems="center" gap={4}>
                          <FileText size={22} color="$slate500" />
                          <Paragraph fontSize={9} color="$slate500">{ev.type}</Paragraph>
                        </YStack>
                      )}
                    </YStack>
                  ))}
                </XStack>
              )}
            </YStack>

            <YStack gap={8}>
              <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">Messages</Paragraph>
              {(data.messages ?? []).length === 0 ? (
                <Paragraph fontSize={13} color="$slate500">Aucun message pour l'instant.</Paragraph>
              ) : (
                (data.messages ?? []).map((m) => {
                  const isMine = m.sender_id === me.data?.id;
                  const time = (() => {
                    try {
                      return format(new Date(m.created_at), 'd MMM HH:mm', { locale: fr });
                    } catch {
                      return '';
                    }
                  })();
                  return (
                    <XStack key={m.id} justifyContent={isMine ? 'flex-end' : 'flex-start'}>
                      <YStack maxWidth="78%" padding={10} borderRadius={12} backgroundColor={isMine ? brand.primary : brand.slate100} gap={3}>
                        <Paragraph fontSize={13.5} color={isMine ? 'white' : '$slate900'}>{m.body}</Paragraph>
                        <Paragraph fontSize={10} color={isMine ? 'rgba(255,255,255,0.8)' : '$slate500'} alignSelf="flex-end">
                          {time}
                        </Paragraph>
                      </YStack>
                    </XStack>
                  );
                })
              )}
            </YStack>
          </ScrollView>

          <XStack
            paddingHorizontal={12}
            paddingVertical={10}
            paddingBottom={insets.bottom + 10}
            gap={8}
            borderTopWidth={1}
            borderTopColor="$slate300"
            backgroundColor="$background"
          >
            <TextInput
              value={text}
              onChangeText={setText}
              placeholder="Ajouter un message…"
              placeholderTextColor={brand.slate500}
              multiline
              style={{
                flex: 1,
                borderWidth: 1,
                borderColor: brand.slate300,
                borderRadius: 18,
                paddingHorizontal: 14,
                paddingVertical: 9,
                fontSize: 14,
                color: brand.slate900,
                maxHeight: 110,
                backgroundColor: brand.slate100,
              }}
            />
            <Pressable onPress={handleSend} disabled={!text.trim() || send.isPending} hitSlop={6}>
              <YStack
                width={42}
                height={42}
                borderRadius={21}
                backgroundColor={!text.trim() ? brand.slate300 : brand.primary}
                alignItems="center"
                justifyContent="center"
              >
                {send.isPending ? <ActivityIndicator color="white" size="small" /> : <Send size={18} color="white" />}
              </YStack>
            </Pressable>
          </XStack>
        </YStack>
      </KeyboardAvoidingView>
    </>
  );
}
