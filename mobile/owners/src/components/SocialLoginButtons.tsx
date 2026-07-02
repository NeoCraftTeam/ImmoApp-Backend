import { Github } from '@tamagui/lucide-icons';
import { useState } from 'react';
import { Alert } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { useSession, type SocialProvider } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';

interface Props {
  /** Called after a successful social sign-in (navigate to dashboard). */
  onSuccess: () => void;
  disabled?: boolean;
}

/**
 * Boutons de connexion sociale bailleur (Google / Facebook / GitHub) —
 * même flux que l'app visitors mais avec `role=agent` porté par
 * `SessionProvider.signInWithProvider`. Pas de `react-native-svg`
 * dans ce workspace : les logos sont des monogrammes colorés (l'icône
 * GitHub vient de lucide).
 */
export function SocialLoginButtons({ onSuccess, disabled }: Props) {
  const { signInWithProvider } = useSession();
  const [pending, setPending] = useState<SocialProvider | null>(null);

  const handle = async (provider: SocialProvider) => {
    if (pending) {
      return;
    }
    setPending(provider);
    try {
      const { cancelled } = await signInWithProvider(provider);
      if (!cancelled) {
        onSuccess();
      }
    } catch (err) {
      Alert.alert('Connexion impossible', extractApiErrorMessage(err));
    } finally {
      setPending(null);
    }
  };

  const providers: {
    key: SocialProvider;
    label: string;
    icon: React.ReactNode;
  }[] = [
    {
      key: 'google',
      label: 'Continuer avec Google',
      icon: (
        <Paragraph fontSize={18} fontWeight="900" color="#4285F4" lineHeight={20}>
          G
        </Paragraph>
      ),
    },
    {
      key: 'facebook',
      label: 'Continuer avec Facebook',
      icon: (
        <Paragraph fontSize={18} fontWeight="900" color="#1877F2" lineHeight={20}>
          f
        </Paragraph>
      ),
    },
    {
      key: 'github',
      label: 'Continuer avec GitHub',
      icon: <Github size={20} color={brand.slate700} />,
    },
  ];

  return (
    <YStack gap="$3">
      <XStack alignItems="center" gap="$3">
        <YStack flex={1} height={1} backgroundColor="$borderColor" />
        <Paragraph size="$2" color="$slate500">
          ou
        </Paragraph>
        <YStack flex={1} height={1} backgroundColor="$borderColor" />
      </XStack>

      {providers.map(({ key, label, icon }) => (
        <XStack
          key={key}
          alignItems="center"
          justifyContent="center"
          gap="$3"
          height={50}
          borderRadius={12}
          borderWidth={1}
          borderColor="$borderColor"
          backgroundColor="$background"
          opacity={disabled || (pending && pending !== key) ? 0.5 : 1}
          pressStyle={{ opacity: 0.7 }}
          onPress={() => {
            if (!disabled) {
              void handle(key);
            }
          }}
          accessibilityRole="button"
          accessibilityLabel={label}
          accessibilityState={{ disabled: Boolean(disabled), busy: pending === key }}
        >
          {pending === key ? <Spinner /> : icon}
          <Paragraph fontSize={15} fontWeight="600" color="$slate900">
            {label}
          </Paragraph>
        </XStack>
      ))}
    </YStack>
  );
}
