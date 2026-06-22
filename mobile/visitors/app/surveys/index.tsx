import { ArrowLeft, ChevronRight, ClipboardList } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { ActivityIndicator, FlatList, Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useSurveys } from '@/hooks/useSurveys';
import { brand } from '@/theme/tokens';
import type { Survey } from '@/types/survey';

export default function SurveysList() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { data, isLoading, isError, error, refetch, isRefetching } = useSurveys();

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
              <ArrowLeft size={18} color={brand.slate700} />
            </YStack>
          </Pressable>
          <Paragraph fontSize={20} fontWeight="700" color="$slate900" flex={1}>
            Sondages
          </Paragraph>
        </XStack>

        {isLoading ? (
          <YStack flex={1} alignItems="center" justifyContent="center"><ActivityIndicator /></YStack>
        ) : isError ? (
          <YStack padding="$5"><Paragraph color="$slate700">{extractApiErrorMessage(error)}</Paragraph></YStack>
        ) : (
          <FlatList
            data={data ?? []}
            keyExtractor={(item) => item.id}
            contentContainerStyle={{ paddingHorizontal: 16, paddingVertical: 14, paddingBottom: insets.bottom + 24, gap: 10 }}
            onRefresh={() => refetch()}
            refreshing={isRefetching}
            ListEmptyComponent={
              <YStack padding="$6" alignItems="center" gap={6}>
                <ClipboardList size={32} color={brand.slate500} />
                <Paragraph fontSize={14} fontWeight="700" color="$slate900">Aucun sondage actif</Paragraph>
                <Paragraph fontSize={12} color="$slate500" textAlign="center">
                  Revenez bientôt — nous publions régulièrement des sondages pour améliorer KeyHome.
                </Paragraph>
              </YStack>
            }
            renderItem={({ item }) => <SurveyRow survey={item} onPress={() => router.push(`/surveys/${item.slug}` as never)} />}
          />
        )}
      </YStack>
    </>
  );
}

function SurveyRow({ survey, onPress }: { survey: Survey; onPress: () => void }) {
  return (
    <Pressable onPress={onPress}>
      <XStack padding={14} borderRadius={12} borderWidth={1} borderColor="$slate300" alignItems="center" gap={12} backgroundColor="$background">
        <YStack
          width={40}
          height={40}
          borderRadius={20}
          backgroundColor={brand.primaryAlpha10}
          alignItems="center"
          justifyContent="center"
        >
          <ClipboardList size={20} color={brand.primary} />
        </YStack>
        <YStack flex={1} gap={2}>
          <Paragraph fontSize={14.5} fontWeight="700" color="$slate900" numberOfLines={1}>{survey.title}</Paragraph>
          <Paragraph fontSize={12} color="$slate500" numberOfLines={2}>
            {survey.description ?? `${(survey.questions ?? []).length} question${(survey.questions ?? []).length > 1 ? 's' : ''}`}
          </Paragraph>
        </YStack>
        <ChevronRight size={16} color={brand.slate500} />
      </XStack>
    </Pressable>
  );
}
