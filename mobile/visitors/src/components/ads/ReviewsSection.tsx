import { Star } from '@tamagui/lucide-icons';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { useState } from 'react';
import { ActivityIndicator, Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { useReviews } from '@/hooks/useReviews';
import { brand } from '@/theme/tokens';
import type { Review } from '@/types/review';

import { ReviewForm } from './ReviewForm';

interface Props {
  adId: string;
  /** Server-provided rating (preferred) — falls back to the local average if absent. */
  fallbackRating?: number | null;
  fallbackCount?: number;
}

/**
 * Reviews section — lists at most 3 reviews inline with an "Afficher
 * tout" toggle for the rest. A pull-down rating header sits at the
 * top with the average score + total count. The `ReviewForm` slot
 * sits below the list when the visitor is logged in and hasn't
 * already reviewed.
 */
export function ReviewsSection({ adId, fallbackRating, fallbackCount }: Props) {
  const { data, isLoading, isError } = useReviews(adId);
  const [showAll, setShowAll] = useState(false);

  const reviews = data?.reviews ?? [];
  const average = data?.averageRating ?? fallbackRating ?? null;
  const count = data?.count ?? fallbackCount ?? reviews.length;

  if (isLoading) {
    return (
      <YStack alignItems="center" paddingVertical={16}>
        <ActivityIndicator />
      </YStack>
    );
  }

  if (isError) {
    return (
      <Paragraph fontSize={13} color="$slate500">
        Impossible de charger les avis pour le moment.
      </Paragraph>
    );
  }

  const visible = showAll ? reviews : reviews.slice(0, 2);

  return (
    <YStack gap={10}>
      <XStack alignItems="center" gap={8}>
        <Paragraph fontSize={16} fontWeight="700" color="$slate900">
          Avis
        </Paragraph>
        {average != null ? (
          <XStack alignItems="center" gap={4}>
            <Star size={14} color="$slate900" fill={brand.slate900} />
            <Paragraph fontSize={14} fontWeight="700" color="$slate900">
              {average.toFixed(1)}
            </Paragraph>
            <Paragraph fontSize={13} color="$slate500">
              · {count} avis
            </Paragraph>
          </XStack>
        ) : (
          <Paragraph fontSize={13} color="$slate500">
            Pas encore noté
          </Paragraph>
        )}
      </XStack>

      {reviews.length === 0 ? (
        <Paragraph fontSize={13} color="$slate500">
          Soyez le premier à laisser un avis sur cette annonce.
        </Paragraph>
      ) : (
        <YStack gap={10}>
          {visible.map((review) => (
            <ReviewItem key={review.id} review={review} />
          ))}
          {reviews.length > 2 && (
            <Pressable onPress={() => setShowAll((s) => !s)} hitSlop={6}>
              <Paragraph
                fontSize={13}
                fontWeight="700"
                color="$slate900"
                textDecorationLine="underline"
              >
                {showAll
                  ? 'Réduire'
                  : `Afficher les ${reviews.length} avis ›`}
              </Paragraph>
            </Pressable>
          )}
        </YStack>
      )}

      <ReviewForm adId={adId} />
    </YStack>
  );
}

function ReviewItem({ review }: { review: Review }) {
  const initial = review.user?.firstname?.charAt(0)?.toUpperCase() ?? '?';
  let relative: string | null = null;
  try {
    relative = formatDistanceToNow(new Date(review.created_at), {
      addSuffix: true,
      locale: fr,
    });
  } catch {
    relative = null;
  }

  return (
    <YStack gap={4}>
      <XStack alignItems="center" gap={8}>
        <YStack
          width={30}
          height={30}
          borderRadius={15}
          backgroundColor={brand.primaryAlpha10}
          alignItems="center"
          justifyContent="center"
        >
          <Paragraph fontSize={13} fontWeight="700" color={brand.primary}>
            {initial}
          </Paragraph>
        </YStack>
        <Paragraph fontSize={13.5} fontWeight="700" color="$slate900">
          {review.user?.firstname ?? 'Utilisateur'}
        </Paragraph>
        <Stars rating={review.rating} size={12} />
        {relative && (
          <Paragraph fontSize={12} color="$slate500">
            · {relative}
          </Paragraph>
        )}
      </XStack>
      {review.comment ? (
        <Paragraph fontSize={13} color="$slate700" lineHeight={18} paddingLeft={38}>
          {review.comment}
        </Paragraph>
      ) : null}
      {review.response?.body ? (
        <YStack
          paddingLeft={38}
          paddingTop={2}
          marginLeft={4}
          borderLeftWidth={2}
          borderLeftColor="$slate300"
        >
          <Paragraph fontSize={12} fontWeight="700" color="$slate900">
            Réponse du bailleur
          </Paragraph>
          <Paragraph fontSize={13} color="$slate700" lineHeight={19}>
            {review.response.body}
          </Paragraph>
        </YStack>
      ) : null}
    </YStack>
  );
}

export function Stars({ rating, size = 14 }: { rating: number; size?: number }) {
  return (
    <XStack alignItems="center" gap={1}>
      {[1, 2, 3, 4, 5].map((i) => (
        <Star
          key={i}
          size={size}
          color={brand.warning}
          fill={i <= Math.round(rating) ? brand.warning : 'transparent'}
        />
      ))}
    </XStack>
  );
}
