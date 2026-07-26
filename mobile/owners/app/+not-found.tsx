import { Stack, useRouter } from 'expo-router';
import { Pressable } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';

/**
 * Filet de sécurité pour les deep-links inconnus (notification obsolète,
 * lien mal formé) : jamais d'écran « Unmatched Route » brut.
 */
export default function NotFoundScreen() {
  const router = useRouter();

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} alignItems="center" justifyContent="center" padding={24} gap={14} backgroundColor="$background">
        <H2 fontSize={22} fontWeight="800" color="$slate900" textAlign="center">
          Page introuvable
        </H2>
        <Paragraph fontSize={14} color="$slate500" textAlign="center" lineHeight={20}>
          Le lien que vous avez suivi n’existe pas ou n’est plus disponible.
        </Paragraph>
        <Pressable onPress={() => router.replace('/(tabs)/dashboard')} accessibilityRole="button">
          <XStack backgroundColor="$brand" paddingHorizontal={22} paddingVertical={12} borderRadius={12}>
            <Paragraph color="$brandText" fontWeight="800">Retour au tableau de bord</Paragraph>
          </XStack>
        </Pressable>
      </YStack>
    </>
  );
}
