import { Info } from '@tamagui/lucide-icons';
import { ActivityIndicator } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { useKeyScore } from '@/hooks/useKeyScore';
import { useThemeColors } from '@/theme/useThemeColors';
import { brand } from '@/theme/tokens';

interface Props {
  adId: string;
}

/**
 * KeyScore — circular score (0-100) with a per-criterion breakdown.
 * Mirrors the web `KeyScoreSection`: ring colour shifts red→amber→green
 * by threshold (50 / 75); each breakdown row uses the same colour so
 * the user can scan strengths and weaknesses quickly.
 */
export function KeyScoreSection({ adId }: Props) {
  const colors = useThemeColors();
  const { data, isLoading, isError } = useKeyScore(adId);

  if (isLoading) {
    return (
      <YStack alignItems="center" paddingVertical={16}>
        <ActivityIndicator />
      </YStack>
    );
  }

  if (isError || !data || data.score == null) {
    return null;
  }

  const color = colorFor(data.score);
  const entries = Object.entries(data.breakdown ?? {}).sort(
    ([, a], [, b]) => b.score - a.score,
  );

  return (
    <YStack gap={14}>
      <XStack alignItems="center" gap={12}>
        <ScoreRing score={data.score} color={color} />
        <YStack flex={1} gap={2}>
          <Paragraph fontSize={17} fontWeight="700" color="$slate900">
            KeyScore
          </Paragraph>
          <Paragraph fontSize={13} color="$slate500" lineHeight={18}>
            {data.label ??
              (data.score >= 75
                ? 'Annonce très complète'
                : data.score >= 50
                  ? 'Annonce correcte'
                  : 'Annonce à compléter')}
          </Paragraph>
        </YStack>
      </XStack>

      {entries.length > 0 && (
        <YStack gap={10}>
          {entries.map(([key, item]) => (
            <YStack key={key} gap={4}>
              <XStack justifyContent="space-between" alignItems="center">
                <Paragraph fontSize={13} fontWeight="600" color="$slate900">
                  {item.label ?? humanize(key)}
                </Paragraph>
                <Paragraph fontSize={12} fontWeight="700" color={colorFor(item.score)}>
                  {Math.round(item.score)} %
                </Paragraph>
              </XStack>
              <YStack
                height={6}
                borderRadius={3}
                backgroundColor={colors.track}
                overflow="hidden"
              >
                <YStack
                  height="100%"
                  width={`${Math.max(0, Math.min(100, item.score))}%`}
                  backgroundColor={colorFor(item.score)}
                />
              </YStack>
            </YStack>
          ))}
        </YStack>
      )}

      <XStack alignItems="center" gap={6}>
        <Info size={12} color="$slate500" />
        <Paragraph fontSize={11} color="$slate500" flex={1}>
          Le KeyScore évalue la complétude d'une annonce (photos,
          description, prix, équipements…).
        </Paragraph>
      </XStack>
    </YStack>
  );
}

function ScoreRing({ score, color }: { score: number; color: string }) {
  // Pure-JS ring: outer circle with a partial pie overlay via a
  // conic-gradient-like approach. RN doesn't ship SVG, so we render
  // a stacked circle pair — outer dim, inner full — and let the
  // numeric centre carry the precise score.
  return (
    <YStack
      width={72}
      height={72}
      borderRadius={36}
      borderWidth={6}
      borderColor={color}
      alignItems="center"
      justifyContent="center"
      backgroundColor="$background"
    >
      <Paragraph fontSize={20} fontWeight="800" color={color} lineHeight={22}>
        {Math.round(score)}
      </Paragraph>
      <Paragraph fontSize={9} color="$slate500" lineHeight={12}>
        / 100
      </Paragraph>
    </YStack>
  );
}

function colorFor(score: number): string {
  if (score >= 75) return brand.success;
  if (score >= 50) return brand.warning;
  return brand.danger;
}

function humanize(key: string): string {
  return key
    .replace(/[_-]/g, ' ')
    .replace(/^./, (s) => s.toUpperCase());
}
