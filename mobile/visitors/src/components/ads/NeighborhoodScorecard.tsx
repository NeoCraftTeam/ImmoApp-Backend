import { ActivityIndicator } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { useNeighborhoodScorecard } from '@/hooks/useNeighborhoodScorecard';
import { useThemeColors } from '@/theme/useThemeColors';
import { brand } from '@/theme/tokens';
import { formatDistance, walkingMinutes } from '@/utils/geo';
import { normalizeCategories, overallScore } from '@/utils/scorecard';

interface Props {
  adId: string;
}

/**
 * Compact mobile mirror of the web `NeighborhoodScorecard` — global
 * score + per-category bars + nearest POI line (distance réelle du
 * backend + minutes à pied estimées). The full PSV map of the web
 * version is intentionally omitted; the map block on the detail page
 * already conveys the spatial context.
 */
export function NeighborhoodScorecard({ adId }: Props) {
  const colors = useThemeColors();
  const { data, isLoading, isError } = useNeighborhoodScorecard(adId);

  if (isLoading) {
    return (
      <YStack alignItems="center" paddingVertical={16}>
        <ActivityIndicator />
      </YStack>
    );
  }

  const categories = normalizeCategories(data?.categories);
  if (isError || !data || data.status === 'unavailable' || categories.length === 0) {
    return null;
  }

  const overall = overallScore(data, categories);
  const color = colorFor(overall);

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
            {overall}
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
        {categories.map((cat) => {
          const distanceKm =
            cat.nearest_poi?.distance_m != null
              ? cat.nearest_poi.distance_m / 1000
              : null;
          return (
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
                backgroundColor={colors.track}
                overflow="hidden"
              >
                <YStack
                  height="100%"
                  width={`${Math.max(0, Math.min(100, cat.score))}%`}
                  backgroundColor={colorFor(cat.score)}
                />
              </YStack>
              {cat.nearest_poi?.name ? (
                <Paragraph fontSize={11.5} color="$slate500">
                  Plus proche : {cat.nearest_poi.name}
                  {distanceKm != null ? ` · ${formatDistance(distanceKm)}` : ''}
                  {distanceKm != null
                    ? ` · ${walkingMinutes(distanceKm)} min à pied`
                    : ''}
                </Paragraph>
              ) : null}
            </YStack>
          );
        })}
      </YStack>
    </YStack>
  );
}

function colorFor(score: number): string {
  if (score >= 75) return brand.success;
  if (score >= 50) return brand.warning;
  return brand.danger;
}
