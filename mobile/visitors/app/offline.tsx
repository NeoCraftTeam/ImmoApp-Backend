import { ArrowLeft, WifiOff } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { Pressable } from 'react-native';
import { Button, H2, Paragraph, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';

export default function Offline() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack
        flex={1}
        backgroundColor="$background"
        paddingTop={insets.top + 12}
        paddingHorizontal={24}
        paddingBottom={insets.bottom + 16}
      >
        <Pressable onPress={() => router.back()} hitSlop={8} accessibilityRole="button" accessibilityLabel="Retour">
          <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
            <ArrowLeft size={18} color="$slate700" />
          </YStack>
        </Pressable>
        <YStack flex={1} alignItems="center" justifyContent="center" gap={14}>
          <YStack width={84} height={84} borderRadius={42} backgroundColor="$slate100" alignItems="center" justifyContent="center">
            <WifiOff size={36} color="$slate500" />
          </YStack>
          <H2 fontSize={22} fontWeight="700" textAlign="center">Vous êtes hors ligne</H2>
          <Paragraph fontSize={14} color="$slate500" textAlign="center" lineHeight={20}>
            Vérifiez votre connexion Wi-Fi ou vos données mobiles puis réessayez.
          </Paragraph>
          <Button backgroundColor="$brand" color="white" fontWeight="700" borderRadius={12} marginTop={10} onPress={() => router.replace('/(tabs)/home')}>
            Réessayer
          </Button>
        </YStack>
      </YStack>
    </>
  );
}
