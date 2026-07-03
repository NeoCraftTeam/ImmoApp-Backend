import { ArrowLeft, Building2, CheckCircle2, Shield, Star, Users } from '@tamagui/lucide-icons';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Image } from 'expo-image';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { ActivityIndicator, FlatList, Pressable } from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { AdCard } from '@/components/AdCard';
import { useAgency } from '@/hooks/useAgency';
import { brand } from '@/theme/tokens';

/**
 * Public agency profile — mirrors the `bailleurs` screen but with a
 * logo card and an "agents" line in the trust signals. Listings are
 * surfaced as a 2-column grid below.
 */
export default function AgencyProfile() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { data, isLoading, isError, error } = useAgency(id);

  if (isLoading) {
    return (
      <YStack flex={1} backgroundColor="$background" justifyContent="center" alignItems="center">
        <ActivityIndicator />
      </YStack>
    );
  }

  if (isError || !data) {
    return (
      <YStack flex={1} backgroundColor="$background" justifyContent="center" alignItems="center" padding="$5" gap="$3">
        <Paragraph color="$slate700" textAlign="center">
          {extractApiErrorMessage(error)}
        </Paragraph>
        <Button onPress={() => router.back()}>Retour</Button>
      </YStack>
    );
  }

  const memberSince = data.created_at
    ? formatDistanceToNow(new Date(data.created_at), {
        addSuffix: true,
        locale: fr,
      })
    : null;

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$background">
        <XStack
          paddingTop={insets.top + 8}
          paddingHorizontal={14}
          paddingBottom={10}
          alignItems="center"
          gap={10}
          borderBottomWidth={1}
          borderBottomColor="$slate300"
        >
          <Pressable onPress={() => router.back()} hitSlop={8} accessibilityRole="button" accessibilityLabel="Retour">
            <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
              <ArrowLeft size={18} color="$slate700" />
            </YStack>
          </Pressable>
          <Paragraph fontSize={16} fontWeight="700" color="$slate900" flex={1}>
            {data.name}
          </Paragraph>
        </XStack>

        <FlatList
          data={data.ads ?? []}
          keyExtractor={(item) => item.id}
          numColumns={2}
          columnWrapperStyle={{ gap: 12, marginBottom: 16 }}
          contentContainerStyle={{ paddingHorizontal: 12, paddingBottom: insets.bottom + 24 }}
          ListHeaderComponent={
            <YStack paddingHorizontal={8} paddingVertical={18} gap={16}>
              <XStack alignItems="center" gap={14}>
                <YStack
                  width={84}
                  height={84}
                  borderRadius={16}
                  backgroundColor={brand.slate100}
                  alignItems="center"
                  justifyContent="center"
                  overflow="hidden"
                >
                  {data.logo ? (
                    <Image source={{ uri: data.logo }} style={{ width: '100%', height: '100%' }} contentFit="cover" />
                  ) : (
                    <Building2 size={36} color="$slate500" />
                  )}
                </YStack>
                <YStack flex={1} gap={4}>
                  <XStack alignItems="center" gap={6}>
                    <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
                      {data.name}
                    </H2>
                    {data.is_verified && <CheckCircle2 size={18} color={brand.success} />}
                  </XStack>
                  {data.city && (
                    <Paragraph fontSize={13} color="$slate500">{data.city}</Paragraph>
                  )}
                  {memberSince && (
                    <Paragraph fontSize={12} color="$slate500">Sur KeyHome {memberSince}</Paragraph>
                  )}
                </YStack>
              </XStack>

              <XStack gap={10} flexWrap="wrap">
                {data.rating != null && (
                  <Chip
                    icon={<Star size={14} color={brand.warning} fill={brand.warning} />}
                    label={`${data.rating.toFixed(1)} (${data.reviews_count ?? 0})`}
                  />
                )}
                {data.trust_score != null && (
                  <Chip
                    icon={<Shield size={14} color={brand.success} />}
                    label={`Trust ${Math.round(data.trust_score)}`}
                  />
                )}
                {(data.agents_count ?? 0) > 0 && (
                  <Chip
                    icon={<Users size={14} color="$slate700" />}
                    label={`${data.agents_count} agent${(data.agents_count ?? 0) > 1 ? 's' : ''}`}
                  />
                )}
                {(data.ads_count ?? 0) > 0 && (
                  <Chip
                    label={`${data.ads_count} annonce${(data.ads_count ?? 0) > 1 ? 's' : ''}`}
                  />
                )}
              </XStack>

              {data.description && (
                <Paragraph fontSize={14} color="$slate700" lineHeight={22}>
                  {data.description}
                </Paragraph>
              )}

              <Paragraph fontSize={15} fontWeight="700" color="$slate900" marginTop={6}>
                Annonces ({data.ads_count ?? data.ads?.length ?? 0})
              </Paragraph>
            </YStack>
          }
          ListEmptyComponent={
            <YStack padding="$5" alignItems="center">
              <Paragraph color="$slate500" textAlign="center">
                Aucune annonce publiée pour le moment.
              </Paragraph>
            </YStack>
          }
          renderItem={({ item, index }) => (
            <YStack flex={1}>
              <AdCard ad={item} priority={index < 2} />
            </YStack>
          )}
        />
      </YStack>
    </>
  );
}

function Chip({ icon, label }: { icon?: React.ReactNode; label: string }) {
  return (
    <XStack
      alignItems="center"
      gap={4}
      paddingHorizontal={10}
      paddingVertical={5}
      borderRadius={999}
      backgroundColor="$slate100"
    >
      {icon}
      <Paragraph fontSize={12} fontWeight="700" color="$slate700">{label}</Paragraph>
    </XStack>
  );
}
