import { ActivityIndicator } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { useNeighborhoodScorecard } from '@/hooks/useNeighborhoodScorecard';
import { brand } from '@/theme/tokens';
import { formatDistance } from '@/utils/geo';
import type { ScorecardCategory } from '@/types/scorecard';

interface Props {
  adId: string;
}

function normalizeCategories(input: unknown): ScorecardCategory[] {
  if (Array.isArray(input)) {
    return input as ScorecardCategory[];
  }
  if (input && typeof input === 'object') {
    return Object.entries(input as Record<string, Partial<ScorecardCategory>>).map(
      ([key, value]) => ({
        key,
        label: value?.label ?? humanizeKey(key),
        score: value?.score ?? 0,
        poi_count: value?.poi_count,
        nearest: value?.nearest ?? null,
      }),
    );
  }
  return [];
}

function humanizeKey(key: string): string {
  return key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
}

/**
 * Compact mobile mirror of the web `NeighborhoodScorecard` — global
 * score + per-category bars + nearest POI line. The full PSV map of
 * the web version is intentionally omitted; the map block on the
 * detail page already conveys the spatial context.
 */
export function NeighborhoodScorecard({ adId }: Props) {
  const { data, isLoading, isError } = useNeighborhoodScorecard(adId);

  if (isLoading) {
    return (
      <YStack alignItems="center" paddingVertical={16}>
        <ActivityIndicator />
      </YStack>
    );
  }

  const categories = normalizeCategories(data?.categories);
  if (isError || !data || data.unavailable || categories.length === 0) {
    return null;
  }

  const color = colorFor(data.overall_score ?? 0);

  return (
    <YStack gap={14}>
      <XStack alignItems="center" gap={12}>
        <YStack
          width={64}
          height={64}
          borderRadius={32}
          borderWidth={5}
          borderColor={color}
          alignItems="center"
          justifyContent="center"
          backgroundColor="$background"
        >
          <Paragraph fontSize={18} fontWeight="800" color={color}>
            {Math.round(data.overall_score)}
          </Paragraph>
        </YStack>
        <YStack flex={1} gap={2}>
          <Paragraph fontSize={17} fontWeight="700" color="$slate900">
            Le quartier
          </Paragraph>
          <Paragraph fontSize={13} color="$slate500">
            Transports, commerces, santé, éducation, sécurité, loisirs.
          </Paragraph>
        </YStack>
      </XStack>

      <YStack gap={10}>
        {categories.map((cat) => (
          <YStack key={cat.key} gap={3}>
            <XStack justifyContent="space-between" alignItems="center">
              <Paragraph fontSize={13} fontWeight="600" color="$slate900">
                {cat.label}
              </Paragraph>
              <Paragraph fontSize={12} fontWeight="700" color={colorFor(cat.score)}>
                {Math.round(cat.score)} %
              </Paragraph>
            </XStack>
            <YStack
              height={5}
              borderRadius={3}
              backgroundColor="$slate100"
              overflow="hidden"
            >
              <YStack
                height="100%"
                width={`${Math.max(0, Math.min(100, cat.score))}%`}
                backgroundColor={colorFor(cat.score)}
              />
            </YStack>
            {cat.nearest?.name && (
              <Paragraph fontSize={11.5} color="$slate500">
                Plus proche : {cat.nearest.name}
                {cat.nearest.distance_m != null
                  ? ` · ${formatDistance(cat.nearest.distance_m / 1000)}`
                  : ''}
                {cat.nearest.walking_minutes != null
                  ? ` · ${cat.nearest.walking_minutes} min à pied`
                  : ''}
              </Paragraph>
            )}
          </YStack>
        ))}
      </YStack>
    </YStack>
  );
}

function colorFor(score: number): string {
  if (score >= 75) return brand.success;
  if (score >= 50) return brand.warning;
  return brand.danger;
}
