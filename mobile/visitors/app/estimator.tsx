import { ArrowLeft, Calculator } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView } from 'react-native';
import {
  Button,
  H2,
  H4,
  Input,
  Paragraph,
  Separator,
  Spinner,
  XStack,
  YStack,
} from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useAdTypes, useCitiesList } from '@/hooks/useCitiesAndTypes';
import { useRentEstimate } from '@/hooks/useRentEstimate';
import { t } from '@/i18n';

/**
 * Rent estimator — mirrors the web `RentEstimatorWidget`. Visitor
 * picks a city + property type + surface (m²), submits, and gets back
 * a min/median/max range. When the backend flags the estimate as
 * `is_unreliable` (sample < 5) we surface an "indicative" info banner
 * so users don't read thin-sample noise as a confident forecast.
 *
 * Accessible from the Account tab and (in a later iteration) from the
 * ad detail page as a "Combien dans ce quartier ?" link.
 */
export default function EstimatorScreen() {
  const insets = useSafeAreaInsets();
  const router = useRouter();

  const [cityId, setCityId] = useState<string>('');
  const [typeId, setTypeId] = useState<string>('');
  const [surface, setSurface] = useState<string>('50');

  const { data: cities, isLoading: loadingCities } = useCitiesList();
  const { data: types, isLoading: loadingTypes } = useAdTypes();
  const estimate = useRentEstimate();
  const result = estimate.data;

  const canSubmit =
    cityId !== '' && typeId !== '' && Number(surface) >= 10 && Number(surface) <= 10000;

  const handleSubmit = () => {
    if (!canSubmit) return;
    estimate.mutate({
      city_id: cityId,
      type_id: typeId,
      surface: Number(surface),
    });
  };

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$background" paddingTop={insets.top + 8}>
        <XStack
          paddingHorizontal="$3"
          paddingBottom="$2"
          alignItems="center"
          gap="$3"
        >
          <Pressable
            onPress={() => router.back()}
            hitSlop={8}
            accessibilityRole="button"
            accessibilityLabel={t('common.back')}
          >
            <ArrowLeft size={22} color="$slate700" />
          </Pressable>
          <H4 flex={1}>{t('estimator.title')}</H4>
        </XStack>

        <ScrollView
          contentContainerStyle={{
            paddingHorizontal: 20,
            paddingBottom: insets.bottom + 24,
          }}
        >
          <YStack gap="$3" paddingTop="$2">
            <Paragraph color="$slate500" size="$4">
              {t('estimator.subtitle')}
            </Paragraph>

            <YStack gap="$2">
              <Paragraph size="$3" color="$slate500">
                {t('estimator.city')}
              </Paragraph>
              {loadingCities ? (
                <ActivityIndicator />
              ) : (
                <YStack
                  borderWidth={1}
                  borderColor="$borderColor"
                  borderRadius={8}
                  maxHeight={180}
                  overflow="hidden"
                >
                  <ScrollView nestedScrollEnabled>
                    {(cities ?? []).map((c) => (
                      <Pressable
                        key={c.id}
                        onPress={() => setCityId(c.id)}
                        style={{
                          paddingHorizontal: 12,
                          paddingVertical: 10,
                          backgroundColor:
                            cityId === c.id ? 'rgba(246, 71, 95, 0.10)' : 'transparent',
                        }}
                        accessibilityRole="button"
                        accessibilityState={{ selected: cityId === c.id }}
                      >
                        <Paragraph
                          color={cityId === c.id ? '$brand' : '$slate700'}
                          fontWeight={cityId === c.id ? '700' : '500'}
                        >
                          {c.name}
                        </Paragraph>
                      </Pressable>
                    ))}
                  </ScrollView>
                </YStack>
              )}
            </YStack>

            <YStack gap="$2">
              <Paragraph size="$3" color="$slate500">
                {t('estimator.type')}
              </Paragraph>
              {loadingTypes ? (
                <ActivityIndicator />
              ) : (
                <XStack flexWrap="wrap" gap="$2">
                  {(types ?? []).map((tp) => (
                    <Button
                      key={tp.id}
                      size="$3"
                      backgroundColor={typeId === tp.id ? '$brand' : '$slate100'}
                      color={typeId === tp.id ? '$brandText' : '$slate700'}
                      borderRadius={999}
                      onPress={() => setTypeId(tp.id)}
                      accessibilityRole="button"
                      accessibilityState={{ selected: typeId === tp.id }}
                    >
                      {tp.name}
                    </Button>
                  ))}
                </XStack>
              )}
            </YStack>

            <YStack gap="$2">
              <Paragraph size="$3" color="$slate500">
                {t('estimator.surface')} (m²)
              </Paragraph>
              <Input
                value={surface}
                onChangeText={(v) => setSurface(v.replace(/[^0-9]/g, ''))}
                keyboardType="numeric"
                size="$4"
                placeholder="50"
              />
            </YStack>

            <Button
              size="$5"
              backgroundColor="$brand"
              color="$brandText"
              fontWeight="700"
              icon={estimate.isPending ? <Spinner /> : Calculator}
              disabled={!canSubmit || estimate.isPending}
              onPress={handleSubmit}
            >
              {t('estimator.submit')}
            </Button>

            {/* Results */}
            {estimate.isError && (
              <YStack
                padding={14}
                borderRadius={12}
                backgroundColor="$slate100"
                borderWidth={1}
                borderColor="$danger"
                gap={8}
                alignItems="center"
              >
                <Paragraph color="$danger" textAlign="center" fontSize={13}>
                  {extractApiErrorMessage(estimate.error)}
                </Paragraph>
                <Button
                  size="$3"
                  backgroundColor="$slate900"
                  color="white"
                  fontWeight="700"
                  onPress={handleSubmit}
                >
                  Réessayer
                </Button>
              </YStack>
            )}

            {result?.error && (
              <Paragraph color="$slate500" textAlign="center">
                {t('estimator.notEnoughData')}
              </Paragraph>
            )}

            {result && !result.error && (
              <YStack
                gap="$3"
                paddingTop="$3"
                accessibilityRole="summary"
                accessibilityLabel={t('estimator.rangeFor')}
              >
                <Separator />

                {result.type_scope_matched === false && (
                  <YStack
                    padding="$3"
                    borderRadius={8}
                    backgroundColor="rgba(245, 158, 11, 0.10)"
                  >
                    <Paragraph size="$3" color="$warning">
                      {t('estimator.typeFallback')}
                    </Paragraph>
                  </YStack>
                )}

                {result.is_unreliable && (
                  <YStack
                    padding="$3"
                    borderRadius={8}
                    backgroundColor="rgba(37, 99, 235, 0.10)"
                    accessibilityRole="alert"
                  >
                    <Paragraph size="$3" color="$info">
                      {t('estimator.unreliable')}
                    </Paragraph>
                  </YStack>
                )}

                <XStack gap="$2">
                  <ResultCard
                    label={t('estimator.low')}
                    value={result.estimated_min}
                  />
                  <ResultCard
                    label={t('estimator.median')}
                    value={result.estimated_median}
                    highlight
                  />
                  <ResultCard
                    label={t('estimator.high')}
                    value={result.estimated_max}
                  />
                </XStack>

                {result.sample_count != null && (
                  <Paragraph size="$2" color="$slate500" textAlign="center">
                    {t('estimator.sampleCount', { count: result.sample_count })}
                  </Paragraph>
                )}
              </YStack>
            )}
          </YStack>
        </ScrollView>
      </YStack>
    </>
  );
}

function ResultCard({
  label,
  value,
  highlight,
}: {
  label: string;
  value?: number;
  highlight?: boolean;
}) {
  return (
    <YStack
      flex={1}
      padding="$3"
      borderRadius={12}
      backgroundColor={highlight ? '$brand' : '$slate100'}
      alignItems="center"
      gap="$1"
    >
      <Paragraph
        size="$2"
        color={highlight ? '$brandText' : '$slate500'}
        opacity={highlight ? 0.85 : 1}
      >
        {label}
      </Paragraph>
      <H4 color={highlight ? '$brandText' : '$slate700'}>
        {value != null ? value.toLocaleString('fr-FR') : '—'}
      </H4>
      <Paragraph
        size="$1"
        color={highlight ? '$brandText' : '$slate500'}
        opacity={0.75}
      >
        FCFA / mois
      </Paragraph>
    </YStack>
  );
}
