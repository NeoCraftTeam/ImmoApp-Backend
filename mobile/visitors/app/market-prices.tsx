import { ArrowLeft, RefreshCw, TrendingUp } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable } from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { usePriceIndex } from '@/hooks/usePriceIndex';
import { brand } from '@/theme/tokens';
import type { PriceIndexRow } from '@/hooks/usePriceIndex';

/**
 * Prix du marché — table par ville/quartier, regroupée. Filtre par
 * ville via une rangée de chips. La heatmap web (carte chaude) n'a
 * pas d'équivalent ergonomique mobile dans ce flow, donc on présente
 * directement les médianes en liste.
 */
export default function MarketPrices() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { data, isLoading, isError, error, refetch } = usePriceIndex();
  const [city, setCity] = useState<string | null>(null);

  const cities = useMemo(() => {
    const set = new Set<string>();
    for (const row of data ?? []) set.add(row.city);
    return Array.from(set).sort();
  }, [data]);

  const filtered = useMemo(() => {
    if (!data) return [] as PriceIndexRow[];
    if (!city) return data;
    return data.filter((r) => r.city === city);
  }, [data, city]);

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$background">
        <YStack
          paddingTop={insets.top + 8}
          paddingHorizontal={14}
          paddingBottom={10}
          gap={10}
          borderBottomWidth={1}
          borderBottomColor="$slate300"
        >
          <XStack alignItems="center" gap={10}>
            <Pressable onPress={() => router.back()} hitSlop={8} accessibilityRole="button" accessibilityLabel="Retour">
              <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
                <ArrowLeft size={18} color={brand.slate700} />
              </YStack>
            </Pressable>
            <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>Prix du marché</H2>
          </XStack>
          {cities.length > 0 && (
            <FlatList
              data={['Toutes', ...cities]}
              keyExtractor={(c) => c}
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={{ gap: 8 }}
              renderItem={({ item }) => {
                const active = item === 'Toutes' ? city == null : city === item;
                return (
                  <Pressable onPress={() => setCity(item === 'Toutes' ? null : item)} hitSlop={4}>
                    <XStack paddingHorizontal={14} paddingVertical={7} borderRadius={999} backgroundColor={active ? brand.slate900 : '$slate100'}>
                      <Paragraph fontSize={12.5} fontWeight="700" color={active ? 'white' : '$slate700'}>{item}</Paragraph>
                    </XStack>
                  </Pressable>
                );
              }}
            />
          )}
        </YStack>

        {isLoading ? (
          <YStack flex={1} alignItems="center" justifyContent="center"><ActivityIndicator /></YStack>
        ) : isError ? (
          <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={14}>
            <TrendingUp size={36} color={brand.slate500} />
            <Paragraph color="$slate700" textAlign="center" fontSize={14}>
              {extractApiErrorMessage(error)}
            </Paragraph>
            <Button
              size="$3"
              backgroundColor="$slate900"
              color="white"
              fontWeight="700"
              borderRadius={999}
              icon={<RefreshCw size={14} color="white" />}
              onPress={() => refetch()}
            >
              Réessayer
            </Button>
          </YStack>
        ) : (
          <FlatList
            data={filtered}
            keyExtractor={(item, idx) => `${item.city}-${item.quarter ?? ''}-${item.type ?? ''}-${idx}`}
            contentContainerStyle={{ paddingHorizontal: 16, paddingVertical: 14, paddingBottom: insets.bottom + 24, gap: 8 }}
            ListEmptyComponent={
              <YStack padding="$6" alignItems="center" gap={6}>
                <TrendingUp size={32} color={brand.slate500} />
                <Paragraph fontSize={14} fontWeight="700" color="$slate900">Aucune donnée</Paragraph>
              </YStack>
            }
            renderItem={({ item }) => (
              <XStack
                padding={14}
                borderRadius={12}
                borderWidth={1}
                borderColor="$slate300"
                alignItems="center"
                justifyContent="space-between"
                backgroundColor="$background"
                gap={8}
              >
                <YStack flex={1}>
                  <Paragraph fontSize={14} fontWeight="700" color="$slate900" numberOfLines={1}>
                    {item.quarter ?? item.city}
                  </Paragraph>
                  <Paragraph fontSize={12} color="$slate500" numberOfLines={1}>
                    {[item.city, item.type].filter(Boolean).join(' · ')}
                    {item.ads_count ? ` · ${item.ads_count} annonces` : ''}
                  </Paragraph>
                </YStack>
                <YStack alignItems="flex-end">
                  <Paragraph fontSize={15} fontWeight="800" color="$slate900">
                    {item.median_price.toLocaleString('fr-FR')}
                  </Paragraph>
                  <Paragraph fontSize={10} color="$slate500">{item.currency ?? 'FCFA'} / médian</Paragraph>
                </YStack>
              </XStack>
            )}
          />
        )}
      </YStack>
    </>
  );
}
