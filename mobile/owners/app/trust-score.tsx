import { CheckCircle2, ShieldCheck, XCircle } from '@tamagui/lucide-icons';
import { Alert, Pressable, RefreshControl, ScrollView } from 'react-native';
import { Button, Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { EmptyState } from '@/components/EmptyState';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useTrustScore, useTrustScoreConsent } from '@/hooks/useTrustScore';
import { brand } from '@/theme/tokens';

function scoreColor(score: number): string {
  if (score >= 80) return brand.success;
  if (score >= 60) return brand.primary;
  if (score >= 40) return brand.warning;
  return brand.danger;
}

export default function TrustScoreScreen() {
  const { isAuthenticated } = useSession();
  const { data: state, isLoading, isRefetching, refetch } = useTrustScore(isAuthenticated);
  const consent = useTrustScoreConsent();
  const trust = state?.score ?? null;

  const setConsent = (value: boolean) => {
    consent.mutate(value, {
      onError: (err) => Alert.alert('Action impossible', extractApiErrorMessage(err)),
    });
  };

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
        ) : state?.consentRequired || state?.consentDeclined ? (
          <YStack
            padding={20}
            gap={14}
            borderRadius={20}
            backgroundColor={brand.primaryAlpha10}
            alignItems="center"
          >
            <ShieldCheck size={36} color={brand.primary} />
            <Paragraph fontSize={17} fontWeight="800" color="$slate900" textAlign="center">
              {state.consentDeclined
                ? 'Votre score de confiance est désactivé'
                : 'Activez votre score de confiance'}
            </Paragraph>
            <Paragraph fontSize={13} color="$slate700" lineHeight={19} textAlign="center">
              Le score de confiance analyse votre activité (annonces vérifiées,
              réactivité, avis reçus…) et s'affiche publiquement sur votre profil
              pour rassurer les locataires. Il nécessite votre consentement
              explicite et reste désactivable à tout moment.
            </Paragraph>
            <Button
              size="$4"
              backgroundColor="$brand"
              color="$brandText"
              fontWeight="800"
              borderRadius={12}
              onPress={() => setConsent(true)}
              disabled={consent.isPending}
              icon={consent.isPending ? <Spinner /> : undefined}
            >
              Activer mon score
            </Button>
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

            <Pressable
              onPress={() =>
                Alert.alert(
                  'Désactiver le score ?',
                  'Votre score ne sera plus calculé ni affiché publiquement. Vous pourrez le réactiver à tout moment.',
                  [
                    { text: 'Annuler', style: 'cancel' },
                    {
                      text: 'Désactiver',
                      style: 'destructive',
                      onPress: () => setConsent(false),
                    },
                  ],
                )
              }
              hitSlop={6}
              accessibilityRole="button"
              accessibilityLabel="Désactiver le score de confiance"
            >
              <Paragraph fontSize={12.5} color="$slate500" textAlign="center" textDecorationLine="underline">
                Désactiver mon score de confiance
              </Paragraph>
            </Pressable>
          </>
        )}
      </ScrollView>
    </YStack>
  );
}
