import { Link, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert } from 'react-native';
import { Button, H2, Input, Paragraph, XStack, YStack, Spinner } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { t } from '@/i18n';

/**
 * Login screen — email + password, posts to `/auth/login` via the
 * SessionProvider. On success, the provider's signIn() persists the
 * token and we navigate to `/home`. On failure we surface the
 * backend's French error message in a native Alert (matches the
 * platform's expected error UI).
 *
 * Form validation kept intentionally light here — the backend's
 * Sanctum login already rejects bad creds with a 422 + clear message.
 * Adding zod / react-hook-form for two fields would be overkill;
 * the register screen uses them where the field count justifies it.
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
      router.replace('/(tabs)/home');
    } catch (err) {
      Alert.alert(t('common.error'), extractApiErrorMessage(err));
    } finally {
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
      <YStack gap="$2">
        <H2>{t('auth.loginTitle')}</H2>
        <Paragraph color="$slate500" size="$4">
          {t('auth.loginSubtitle')}
        </Paragraph>
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
          <Input
            value={password}
            onChangeText={setPassword}
            secureTextEntry
            autoCapitalize="none"
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
