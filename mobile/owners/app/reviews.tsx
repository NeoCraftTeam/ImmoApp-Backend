import { MessageSquare, Send, Star, X } from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert, Pressable, RefreshControl, ScrollView, TextInput } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { apiClient, extractApiErrorMessage } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import { brand } from '@/theme/tokens';
import { formatDate } from '@/utils/format';
import type { Review } from '@/types/owner';

function StarRow({ rating }: { rating: number }) {
  return (
    <XStack gap={2}>
      {Array.from({ length: 5 }).map((_, i) => (
        <Star
          key={i}
          size={13}
          color={i < rating ? brand.accent : brand.slate300}
          fill={i < rating ? brand.accent : 'transparent'}
        />
      ))}
    </XStack>
  );
}

/**
 * Avis reçus — alimenté par l'endpoint agrégé `GET /my/reviews`
 * (l'ancien fan-out par annonce est remplacé : l'endpoint existe bel
 * et bien côté backend). Le bailleur peut répondre à chaque avis via
 * `POST /reviews/{id}/respond` (parité web).
 */
export default function ReviewsScreen() {
  const { isAuthenticated } = useSession();
  const qc = useQueryClient();
  const [replyingTo, setReplyingTo] = useState<string | null>(null);
  const [replyDraft, setReplyDraft] = useState('');

  const reviews = useQuery<{ data?: Review[] }, Error, Review[]>({
    queryKey: ['my-reviews'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data?: Review[] }>(
        ENDPOINTS.reviews.mine,
        { params: { per_page: 100 } },
      );
      return data;
    },
    select: (p) => (Array.isArray(p?.data) ? p.data : []),
    enabled: isAuthenticated,
    staleTime: 60 * 1000,
  });

  const respond = useMutation<void, Error, { reviewId: string; response: string }>({
    mutationFn: async ({ reviewId, response }) => {
      await apiClient.post(ENDPOINTS.reviews.respond(reviewId), { response });
    },
    onSuccess: () => {
      setReplyingTo(null);
      setReplyDraft('');
      qc.invalidateQueries({ queryKey: ['my-reviews'] });
    },
    onError: (err) => {
      Alert.alert('Réponse impossible', extractApiErrorMessage(err));
    },
  });

  const submitReply = (reviewId: string) => {
    const response = replyDraft.trim();
    if (response === '') return;
    respond.mutate({ reviewId, response });
  };

  const allReviews = reviews.data ?? [];
  const avg = allReviews.length
    ? allReviews.reduce((acc, r) => acc + (r.rating ?? 0), 0) / allReviews.length
    : 0;

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Avis reçus" />

      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 12 }}
        keyboardShouldPersistTaps="handled"
        refreshControl={
          <RefreshControl
            refreshing={reviews.isRefetching}
            onRefresh={() => reviews.refetch()}
            tintColor={brand.primary}
          />
        }
      >
        {!reviews.isLoading && allReviews.length > 0 ? (
          <YStack
            padding={16}
            gap={6}
            borderRadius={16}
            backgroundColor={brand.accentAlpha10}
            alignItems="center"
          >
            <XStack alignItems="center" gap={6}>
              <Star size={20} color={brand.accent} fill={brand.accent} />
              <Paragraph fontSize={26} fontWeight="900" color="$slate900">
                {avg.toFixed(1)}
              </Paragraph>
              <Paragraph fontSize={13} color="$slate500">
                / 5
              </Paragraph>
            </XStack>
            <Paragraph fontSize={12.5} color="$slate700">
              {allReviews.length} avis sur l'ensemble de vos annonces
            </Paragraph>
          </YStack>
        ) : null}

        {reviews.isLoading ? (
          <YStack height={300} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : allReviews.length === 0 ? (
          <YStack height={300}>
            <EmptyState
              icon={<Star size={28} color={brand.accent} />}
              title="Aucun avis pour le moment"
              hint="Les avis laissés par les locataires apparaîtront ici. Encouragez vos clients satisfaits à partager leur expérience."
            />
          </YStack>
        ) : (
          allReviews.map((r) => {
            const fullName = `${r.user?.firstname ?? ''} ${r.user?.lastname ?? ''}`.trim();
            const isReplying = replyingTo === r.id;
            return (
              <YStack
                key={r.id}
                padding={14}
                gap={8}
                borderRadius={14}
                borderWidth={1}
                borderColor="$slate300"
              >
                <XStack alignItems="center" gap={10}>
                  <YStack width={36} height={36} borderRadius={18} overflow="hidden" backgroundColor="$slate100" alignItems="center" justifyContent="center">
                    {r.user?.avatar ? (
                      <Image source={{ uri: r.user.avatar }} style={{ width: '100%', height: '100%' }} contentFit="cover" />
                    ) : (
                      <Paragraph fontSize={13} fontWeight="800" color={brand.primary}>
                        {(r.user?.firstname?.[0] ?? '?').toUpperCase()}
                      </Paragraph>
                    )}
                  </YStack>
                  <YStack flex={1} gap={2}>
                    <Paragraph fontSize={13.5} fontWeight="700" color="$slate900">
                      {fullName || 'Anonyme'}
                    </Paragraph>
                    <Paragraph fontSize={11} color="$slate500">
                      {formatDate(r.created_at)}
                      {r.ad?.title ? ` · ${r.ad.title}` : ''}
                    </Paragraph>
                  </YStack>
                  <StarRow rating={r.rating ?? 0} />
                </XStack>
                {r.comment ? (
                  <Paragraph fontSize={13} color="$slate700" lineHeight={19}>
                    {r.comment}
                  </Paragraph>
                ) : null}

                {r.owner_response ? (
                  <YStack
                    marginLeft={46}
                    padding={10}
                    borderRadius={10}
                    backgroundColor={brand.primaryAlpha10}
                    gap={3}
                  >
                    <Paragraph fontSize={11.5} fontWeight="800" color={brand.primary}>
                      Votre réponse
                    </Paragraph>
                    <Paragraph fontSize={12.5} color="$slate900">
                      {r.owner_response}
                    </Paragraph>
                  </YStack>
                ) : isReplying ? (
                  <YStack gap={8} marginLeft={46}>
                    <TextInput
                      value={replyDraft}
                      onChangeText={setReplyDraft}
                      placeholder="Votre réponse publique (1000 caractères max)…"
                      placeholderTextColor={brand.slate500}
                      multiline
                      maxLength={1000}
                      autoFocus
                      style={{
                        minHeight: 70,
                        padding: 10,
                        borderRadius: 10,
                        backgroundColor: brand.slate100,
                        color: brand.slate900,
                        fontSize: 13,
                        textAlignVertical: 'top',
                      }}
                    />
                    <XStack gap={8} justifyContent="flex-end">
                      <Pressable
                        onPress={() => {
                          setReplyingTo(null);
                          setReplyDraft('');
                        }}
                        hitSlop={6}
                        accessibilityRole="button"
                        accessibilityLabel="Annuler la réponse"
                      >
                        <XStack alignItems="center" gap={4} paddingHorizontal={12} paddingVertical={8} borderRadius={999} backgroundColor={brand.slate100}>
                          <X size={13} color={brand.slate700} />
                          <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
                            Annuler
                          </Paragraph>
                        </XStack>
                      </Pressable>
                      <Pressable
                        onPress={() => submitReply(r.id)}
                        hitSlop={6}
                        disabled={respond.isPending || replyDraft.trim() === ''}
                        accessibilityRole="button"
                        accessibilityLabel="Publier la réponse"
                      >
                        <XStack
                          alignItems="center"
                          gap={4}
                          paddingHorizontal={12}
                          paddingVertical={8}
                          borderRadius={999}
                          backgroundColor={replyDraft.trim() ? brand.primary : brand.slate300}
                        >
                          {respond.isPending ? (
                            <Spinner size="small" color="white" />
                          ) : (
                            <Send size={13} color="white" />
                          )}
                          <Paragraph fontSize={12.5} fontWeight="700" color="white">
                            Publier
                          </Paragraph>
                        </XStack>
                      </Pressable>
                    </XStack>
                  </YStack>
                ) : (
                  <Pressable
                    onPress={() => {
                      setReplyingTo(r.id);
                      setReplyDraft('');
                    }}
                    hitSlop={6}
                    accessibilityRole="button"
                    accessibilityLabel="Répondre à cet avis"
                  >
                    <XStack alignItems="center" gap={6} marginLeft={46}>
                      <MessageSquare size={14} color={brand.primary} />
                      <Paragraph fontSize={12.5} fontWeight="700" color={brand.primary}>
                        Répondre
                      </Paragraph>
                    </XStack>
                  </Pressable>
                )}
              </YStack>
            );
          })
        )}
      </ScrollView>
    </YStack>
  );
}
