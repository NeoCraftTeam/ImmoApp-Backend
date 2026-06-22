import { ArrowLeft, MapPin } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, FlatList, Pressable } from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { AdCard } from '@/components/AdCard';
import { useNearbyAds } from '@/hooks/useNearbyAds';
import { useUserLocation } from '@/hooks/useUserLocation';
import { brand } from '@/theme/tokens';

const RADII = [2, 5, 10, 20];

/**
 * Nearby tab — uses `expo-location` to drop the user on a radius pill
 * picker, then queries `/ads/nearby` for ads within that radius.
 * Permission-denied state offers a manual retry (the system prompt is
 * usually one-shot per app session).
 */
export default function Nearby() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [radius, setRadius] = useState(5);
  const { location, isLoading: locLoading, permissionDenied } = useUserLocation();

  const { data, isLoading, isError, error, refetch, isRefetching } = useNearbyAds({
    latitude: location?.latitude,
    longitude: location?.longitude,
    radiusKm: radius,
  });

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
              <YStack
                width={36}
                height={36}
                borderRadius={18}
                backgroundColor="$slate100"
                alignItems="center"
                justifyContent="center"
              >
                <ArrowLeft size={18} color={brand.slate700} />
              </YStack>
            </Pressable>
            <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
              Près de moi
            </H2>
          </XStack>
          {location && (
            <XStack gap={8}>
              {RADII.map((r) => (
                <Pressable key={r} onPress={() => setRadius(r)} hitSlop={4}>
                  <XStack
                    paddingHorizontal={14}
                    paddingVertical={7}
                    borderRadius={999}
                    backgroundColor={radius === r ? brand.slate900 : '$slate100'}
                  >
                    <Paragraph fontSize={12.5} fontWeight="700" color={radius === r ? 'white' : '$slate700'}>
                      {r} km
                    </Paragraph>
                  </XStack>
                </Pressable>
              ))}
            </XStack>
          )}
        </YStack>

        {locLoading ? (
          <YStack flex={1} alignItems="center" justifyContent="center">
            <ActivityIndicator />
          </YStack>
        ) : permissionDenied || !location ? (
          <YStack flex={1} alignItems="center" justifyContent="center" gap={10} padding="$5">
            <MapPin size={36} color={brand.slate500} />
            <Paragraph fontSize={15} fontWeight="700" color="$slate900" textAlign="center">
              Activez la localisation
            </Paragraph>
            <Paragraph fontSize={13} color="$slate500" textAlign="center">
              Pour afficher les annonces proches de vous, autorisez l'app à utiliser
              votre position dans les Réglages iOS / Android.
            </Paragraph>
            <Button
              backgroundColor="$brand"
              color="white"
              onPress={() => router.back()}
              marginTop={4}
            >
              Retour
            </Button>
          </YStack>
        ) : isLoading ? (
          <YStack flex={1} alignItems="center" justifyContent="center">
            <ActivityIndicator />
          </YStack>
        ) : isError ? (
          <YStack padding="$5">
            <Paragraph color="$slate700">{extractApiErrorMessage(error)}</Paragraph>
          </YStack>
        ) : (
          <FlatList
            data={data ?? []}
            keyExtractor={(item) => item.id}
            numColumns={2}
            columnWrapperStyle={{ gap: 12, marginBottom: 16 }}
            contentContainerStyle={{
              paddingHorizontal: 12,
              paddingTop: 14,
              paddingBottom: insets.bottom + 24,
            }}
            onRefresh={() => refetch()}
            refreshing={isRefetching}
            ListEmptyComponent={
              <YStack padding="$5" alignItems="center" gap={6}>
                <Paragraph color="$slate500" textAlign="center">
                  Aucune annonce dans un rayon de {radius} km.
                </Paragraph>
              </YStack>
            }
            renderItem={({ item, index }) => (
              <YStack flex={1}>
                <AdCard ad={item} priority={index < 2} />
              </YStack>
            )}
          />
        )}
      </YStack>
    </>
  );
}
