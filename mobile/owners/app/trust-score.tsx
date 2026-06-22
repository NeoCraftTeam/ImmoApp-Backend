import { CheckCircle2, ShieldCheck, XCircle } from '@tamagui/lucide-icons';
import { RefreshControl, ScrollView } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useTrustScore } from '@/hooks/useTrustScore';
import { brand } from '@/theme/tokens';

function scoreColor(score: number): string {
  if (score >= 80) return brand.success;
  if (score >= 60) return brand.primary;
  if (score >= 40) return brand.warning;
  return brand.danger;
}

export default function TrustScoreScreen() {
  const { isAuthenticated } = useSession();
  const { data: trust, isLoading, isRefetching, refetch } = useTrustScore(isAuthenticated);

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Score de confiance" />
      <ScrollView
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 16 }}
        refreshControl={
          <RefreshControl refreshing={isRefetching} onRefresh={refetch} tintColor={brand.primary} />
        }
      >
        {isLoading ? (
          <YStack height={320} alignItems="center" justifyContent="center">
            <Spinner color={brand.primary} size="large" />
          </YStack>
        ) : !trust ? (
          <YStack height={320}>
            <EmptyState
              icon={<ShieldCheck size={28} color={brand.primary} />}
              title="Indisponible"
              hint="Le score de confiance sera bientôt calculé pour votre compte."
            />
          </YStack>
        ) : (
          <>
            <YStack
              padding={20}
              borderRadius={20}
              backgroundColor={brand.primaryAlpha10}
              alignItems="center"
              gap={8}
            >
              <ShieldCheck size={36} color={scoreColor(trust.score)} />
              <Paragraph fontSize={44} fontWeight="900" color={scoreColor(trust.score)}>
                {Math.round(trust.score)}
                <Paragraph fontSize={18} color="$slate500"> / 100</Paragraph>
              </Paragraph>
              {trust.level ? (
                <Paragraph fontSize={13.5} fontWeight="700" color="$slate700">
                  {trust.level}
                </Paragraph>
              ) : null}
            </YStack>

            {trust.factors && trust.factors.length > 0 ? (
              <YStack gap={10}>
                <Paragraph fontSize={14} fontWeight="800" color="$slate900">
                  Facteurs évalués
                </Paragraph>
                {trust.factors.map((f) => {
                  const pct = f.max > 0 ? Math.round((f.value / f.max) * 100) : 0;
                  const color = scoreColor(pct);
                  return (
                    <YStack
                      key={f.key}
                      padding={12}
                      borderRadius={12}
                      borderWidth={1}
                      borderColor="$slate300"
                      gap={6}
                    >
                      <XStack alignItems="center" gap={8}>
                        {pct >= 60 ? (
                          <CheckCircle2 size={16} color={color} />
                        ) : (
                          <XCircle size={16} color={color} />
                        )}
                        <Paragraph fontSize={13} fontWeight="700" color="$slate900" flex={1}>
                          {f.label}
                        </Paragraph>
                        <Paragraph fontSize={12} fontWeight="800" color={color}>
                          {f.value} / {f.max}
                        </Paragraph>
                      </XStack>
                      <YStack height={6} borderRadius={3} backgroundColor="$slate100" overflow="hidden">
                        <YStack height="100%" width={`${pct}%` as unknown as number} backgroundColor={color} />
                      </YStack>
                    </YStack>
                  );
                })}
              </YStack>
            ) : null}

            {trust.recommendations && trust.recommendations.length > 0 ? (
              <YStack
                padding={14}
                gap={8}
                borderRadius={14}
                backgroundColor={brand.accentAlpha10}
              >
                <Paragraph fontSize={13.5} fontWeight="800" color={brand.accentDark}>
                  Pour améliorer votre score
                </Paragraph>
                {trust.recommendations.map((r, i) => (
                  <Paragraph key={i} fontSize={12.5} color="$slate700">
                    • {r}
                  </Paragraph>
                ))}
              </YStack>
            ) : null}
          </>
        )}
      </ScrollView>
    </YStack>
  );
}
