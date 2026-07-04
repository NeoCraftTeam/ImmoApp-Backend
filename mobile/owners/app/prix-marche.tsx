import { useState } from 'react';
import { Calculator, TrendingUp } from '@tamagui/lucide-icons';
import { ScrollView } from 'react-native';
import { Button, Input, Paragraph, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useMarketEstimate } from '@/hooks/useMarketPrice';
import { useCities, useAdTypes } from '@/hooks/useReference';
import { brand } from '@/theme/tokens';
import { formatFcfa } from '@/utils/format';

export default function PrixMarcheScreen() {
  const { data: cities = [] } = useCities();
  const { data: adTypes = [] } = useAdTypes();
  const estimate = useMarketEstimate();

  const [cityId, setCityId] = useState<string>('');
  const [adTypeId, setAdTypeId] = useState<string>('');
  const [bedrooms, setBedrooms] = useState<string>('');
  const [surface, setSurface] = useState<string>('');
  const [transactionType, setTransactionType] = useState<'rent' | 'sale'>('rent');

  const onSubmit = () => {
    // Le backend exige city_id + type_id + surface (≥ 10).
    if (!cityId || !adTypeId || !surface) return;
    estimate.mutate({
      city_id: cityId,
      ad_type_id: adTypeId,
      bedrooms: bedrooms ? Number(bedrooms) : undefined,
      surface: Number(surface),
    });
  };

  const result = estimate.data;

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title="Prix du marché" subtitle="Estimez votre bien en quelques secondes" />

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 14 }}>
        <XStack gap={8}>
          {(['rent', 'sale'] as const).map((tt) => {
            const isActive = transactionType === tt;
            return (
              <Button
                key={tt}
                flex={1}
                size="$3"
                chromeless
                borderRadius={999}
                backgroundColor={isActive ? '$brand' : '$slate100'}
                onPress={() => setTransactionType(tt)}
              >
                <Paragraph fontSize={13} fontWeight="800" color={isActive ? 'white' : '$slate700'}>
                  {tt === 'rent' ? 'Location' : 'Vente'}
                </Paragraph>
              </Button>
            );
          })}
        </XStack>

        <YStack gap={6}>
          <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
            Ville
          </Paragraph>
          <YStack borderWidth={1} borderColor="$slate300" borderRadius={12} maxHeight={180}>
            <ScrollView>
              {cities.map((c) => {
                const isSel = cityId === c.id;
                return (
                  <Button
                    key={c.id}
                    chromeless
                    borderRadius={0}
                    justifyContent="flex-start"
                    backgroundColor={isSel ? brand.primaryAlpha10 : 'transparent'}
                    onPress={() => setCityId(c.id)}
                  >
                    <Paragraph fontSize={13} color={isSel ? brand.primary : '$slate900'} fontWeight={isSel ? '700' : '500'}>
                      {c.name}
                    </Paragraph>
                  </Button>
                );
              })}
            </ScrollView>
          </YStack>
        </YStack>

        <YStack gap={6}>
          <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
            Type de bien
          </Paragraph>
          <XStack gap={6} flexWrap="wrap">
            {adTypes.map((t) => {
              const isSel = adTypeId === t.id;
              return (
                <Button
                  key={t.id}
                  size="$2"
                  chromeless
                  borderRadius={999}
                  backgroundColor={isSel ? '$brand' : '$slate100'}
                  onPress={() => setAdTypeId(t.id)}
                  paddingHorizontal={12}
                >
                  <Paragraph fontSize={12} fontWeight="700" color={isSel ? 'white' : '$slate700'}>
                    {t.name}
                  </Paragraph>
                </Button>
              );
            })}
          </XStack>
        </YStack>

        <XStack gap={10}>
          <YStack flex={1} gap={6}>
            <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
              Chambres
            </Paragraph>
            <Input value={bedrooms} onChangeText={setBedrooms} keyboardType="numeric" placeholder="2" />
          </YStack>
          <YStack flex={1} gap={6}>
            <Paragraph fontSize={12.5} fontWeight="700" color="$slate700">
              Surface (m²)
            </Paragraph>
            <Input value={surface} onChangeText={setSurface} keyboardType="numeric" placeholder="85" />
          </YStack>
        </XStack>

        <Button
          size="$4"
          backgroundColor="$brand"
          color="white"
          fontWeight="800"
          borderRadius={12}
          disabled={!cityId || !adTypeId || !surface || estimate.isPending}
          opacity={!cityId || !adTypeId || !surface ? 0.5 : 1}
          onPress={onSubmit}
          icon={<Calculator size={16} color="white" />}
        >
          {estimate.isPending ? 'Calcul…' : 'Estimer'}
        </Button>

        {estimate.isError ? (
          <Paragraph fontSize={12.5} color={brand.danger}>
            {extractApiErrorMessage(estimate.error)}
          </Paragraph>
        ) : null}

        {result ? (
          <YStack
            marginTop={8}
            padding={18}
            borderRadius={16}
            backgroundColor={brand.primaryAlpha10}
            gap={6}
          >
            <XStack alignItems="center" gap={8}>
              <TrendingUp size={18} color={brand.primary} />
              <Paragraph fontSize={13} fontWeight="700" color={brand.primary}>
                Estimation
              </Paragraph>
            </XStack>
            <Paragraph fontSize={28} fontWeight="900" color="$slate900">
              {formatFcfa(result.estimated_price)}
            </Paragraph>
            {result.range ? (
              <Paragraph fontSize={12.5} color="$slate700">
                Fourchette {formatFcfa(result.range.low)} – {formatFcfa(result.range.high)}
              </Paragraph>
            ) : null}
            {result.comparable_count ? (
              <Paragraph fontSize={11.5} color="$slate500">
                Basé sur {result.comparable_count} biens comparables
              </Paragraph>
            ) : null}
            {result.is_unreliable ? (
              <Paragraph fontSize={11.5} color={brand.warning} fontWeight="700">
                ⚠ Estimation peu fiable (peu de données pour cette zone)
              </Paragraph>
            ) : null}
          </YStack>
        ) : null}
      </ScrollView>
    </YStack>
  );
}
