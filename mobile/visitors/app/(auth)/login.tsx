import * as Haptics from 'expo-haptics';
import { Link, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert } from 'react-native';
import { Button, H2, Input, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { KeyHomeLogo } from '@/components/KeyHomeLogo';
import { PasswordInput } from '@/components/PasswordInput';
import { SocialLoginButtons } from '@/components/SocialLoginButtons';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';

/**
 * Login screen — email + password, posts to `/auth/login` via the
 * SessionProvider. On success we play a brief Lottie-flavoured success
 * overlay (check icon scale-up + green halo fade) for ~900 ms before
 * pushing the user into the tab bar. Haptic success punctuates the
 * tactile feedback so the user *feels* the transition.
 */
export default function Login() {
  const { signIn } = useSession();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleSignIn = async () => {
    if (email.trim() === '' || password === '') {
      Alert.alert(t('common.error'), 'Email + mot de passe requis.');
      return;
    }
    setSubmitting(true);
    try {
      await signIn(email.trim(), password);
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      router.replace('/(tabs)/home');
    } catch (err) {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common.error'), extractApiErrorMessage(err));
      setSubmitting(false);
    }
  };

  return (
    <YStack
      flex={1}
      backgroundColor="$background"
      paddingTop={insets.top + 24}
      paddingHorizontal="$5"
      paddingBottom={insets.bottom + 16}
      gap="$5"
    >
      <YStack alignItems="flex-start" gap="$4">
        <KeyHomeLogo size={22} />
        <YStack gap="$2">
          <H2>{t('auth.loginTitle')}</H2>
          <Paragraph color="$slate500" size="$4">
            {t('auth.loginSubtitle')}
          </Paragraph>
        </YStack>
      </YStack>

      <YStack gap="$3">
        <YStack gap="$1">
          <Paragraph size="$3" color="$slate500">
            {t('auth.email')}
          </Paragraph>
          <Input
            value={email}
            onChangeText={setEmail}
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
            autoComplete="email"
            textContentType="emailAddress"
            placeholder="email@exemple.com"
            size="$4"
          />
        </YStack>

        <YStack gap="$1">
          <Paragraph size="$3" color="$slate500">
            {t('auth.password')}
          </Paragraph>
          <PasswordInput
            value={password}
            onChangeText={setPassword}
            autoComplete="current-password"
            textContentType="password"
            placeholder="••••••••"
            size="$4"
          />
        </YStack>
      </YStack>

      <Button
        size="$5"
        backgroundColor="$brand"
        color="$brandText"
        fontWeight="700"
        onPress={handleSignIn}
        disabled={submitting}
        icon={submitting ? <Spinner /> : undefined}
        accessibilityRole="button"
        accessibilityState={{ disabled: submitting, busy: submitting }}
      >
        {t('auth.signIn')}
      </Button>

      <SocialLoginButtons
        disabled={submitting}
        onSuccess={() => router.replace('/(tabs)/home')}
      />

      <Link href={'/(auth)/forgot-password' as never} asChild>
        <Paragraph color="$brand" fontWeight="600" textAlign="center">
          {t('auth.forgotPassword')}
        </Paragraph>
      </Link>

      <XStack justifyContent="center" gap="$2">
        <Paragraph color="$slate500">{t('auth.noAccount')}</Paragraph>
        <Link href="/(auth)/register" asChild>
          <Paragraph color="$brand" fontWeight="600">
            {t('auth.signUp')}
          </Paragraph>
        </Link>
      </XStack>

      <YStack flex={1} justifyContent="flex-end">
        <Button size="$3" chromeless onPress={() => router.replace('/(tabs)/home')}>
          {t('auth.continueAsGuest')}
        </Button>
      </YStack>

    </YStack>
  );
}
