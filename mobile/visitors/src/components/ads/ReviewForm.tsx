import { Star } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Pressable, TextInput } from 'react-native';
import { Button, Paragraph, XStack, YStack } from 'tamagui';

import { useSession } from '@/auth/SessionProvider';
import { extractApiErrorMessage } from '@/api/client';
import { useCreateReview } from '@/hooks/useReviews';
import { brand } from '@/theme/tokens';

const MAX_LENGTH = 1000;

interface Props {
  adId: string;
}

/**
 * Inline review form — appears below the existing reviews list when
 * the visitor is logged in. Anonymous visitors see a "sign in to
 * leave a review" prompt that deep-links to the auth flow instead.
 *
 * Submit is disabled until the user picks a rating (1-5); the
 * comment is optional but capped at 1000 chars to match the
 * web `ReviewForm.tsx`.
 */
export function ReviewForm({ adId }: Props) {
  const { isAuthenticated } = useSession();
  const router = useRouter();
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState('');
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const mutation = useCreateReview(adId);

  if (submitted) {
    return (
      <YStack
        padding={14}
        borderRadius={12}
        backgroundColor={`${brand.success}15`}
        gap={4}
      >
        <Paragraph fontSize={14} fontWeight="700" color={brand.success}>
          Merci pour votre avis !
        </Paragraph>
        <Paragraph fontSize={13} color="$slate700">
          Votre commentaire est désormais visible.
        </Paragraph>
      </YStack>
    );
  }

  if (!isAuthenticated) {
    return (
      <YStack
        padding={14}
        borderRadius={12}
        backgroundColor="$slate100"
        gap={8}
      >
        <Paragraph fontSize={14} fontWeight="700" color="$slate900">
          Laisser un avis
        </Paragraph>
        <Paragraph fontSize={13} color="$slate500">
          Connectez-vous pour partager votre expérience.
        </Paragraph>
        <Button
          size="$3"
          backgroundColor="$slate900"
          color="white"
          fontWeight="700"
          alignSelf="flex-start"
          onPress={() => router.push('/(auth)/login')}
        >
          Se connecter
        </Button>
      </YStack>
    );
  }

  const handleSubmit = async () => {
    if (rating < 1) return;
    setError(null);
    try {
      await mutation.mutateAsync({
        rating,
        comment: comment.trim() === '' ? undefined : comment.trim(),
      });
      setSubmitted(true);
    } catch (err) {
      setError(extractApiErrorMessage(err));
    }
  };

  return (
    <YStack
      padding={14}
      borderRadius={12}
      borderWidth={1}
      borderColor="$slate300"
      gap={12}
    >
      <Paragraph fontSize={14} fontWeight="700" color="$slate900">
        Laisser un avis
      </Paragraph>
      <XStack gap={6}>
        {[1, 2, 3, 4, 5].map((i) => (
          <Pressable
            key={i}
            onPress={() => setRating(i)}
            hitSlop={4}
            accessibilityRole="button"
            accessibilityLabel={`Noter ${i} étoile${i > 1 ? 's' : ''}`}
          >
            <Star
              size={28}
              color={brand.warning}
              fill={i <= rating ? brand.warning : 'transparent'}
            />
          </Pressable>
        ))}
      </XStack>
      <TextInput
        value={comment}
        onChangeText={setComment}
        placeholder="Décrivez votre expérience (optionnel)"
        placeholderTextColor={brand.slate500}
        multiline
        maxLength={MAX_LENGTH}
        style={{
          minHeight: 80,
          borderWidth: 1,
          borderColor: brand.slate300,
          borderRadius: 8,
          padding: 10,
          fontSize: 14,
          color: brand.slate900,
          textAlignVertical: 'top',
        }}
      />
      <XStack alignItems="center" justifyContent="space-between">
        <Paragraph fontSize={11} color="$slate500">
          {comment.length} / {MAX_LENGTH}
        </Paragraph>
        <Button
          size="$3"
          backgroundColor={rating > 0 ? brand.primary : brand.slate300}
          color="white"
          fontWeight="700"
          disabled={rating < 1 || mutation.isPending}
          onPress={handleSubmit}
        >
          {mutation.isPending ? 'Envoi…' : 'Publier'}
        </Button>
      </XStack>
      {error && (
        <Paragraph fontSize={12} color={brand.danger}>
          {error}
        </Paragraph>
      )}
    </YStack>
  );
}
