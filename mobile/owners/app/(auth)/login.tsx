import { Building2, CheckCircle2 } from '@tamagui/lucide-icons';
import * as Haptics from 'expo-haptics';
import { Link, useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Alert, Animated, Easing } from 'react-native';
import { Button, H1, Input, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { SocialLoginButtons } from '@/components/SocialLoginButtons';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';

/**
 * Owner login — email + password → `/auth/login`. On success a brief
 * check-pop success overlay plays before pushing into the dashboard.
 */
export default function Login() {
  const { signIn } = useSession();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [succeeded, setSucceeded] = useState(false);

  const overlayOpacity = useRef(new Animated.Value(0)).current;
  const checkScale = useRef(new Animated.Value(0.4)).current;
  const checkOpacity = useRef(new Animated.Value(0)).current;
  const successTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    return () => {
      if (successTimeoutRef.current) clearTimeout(successTimeoutRef.current);
    };
  }, []);

  const playSuccess = (onComplete: () => void) => {
    setSucceeded(true);
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    Animated.parallel([
      Animated.timing(overlayOpacity, { toValue: 1, duration: 180, useNativeDriver: true }),
      Animated.spring(checkScale, { toValue: 1, damping: 12, stiffness: 220, useNativeDriver: true }),
      Animated.timing(checkOpacity, { toValue: 1, duration: 220, useNativeDriver: true }),
    ]).start();
    successTimeoutRef.current = setTimeout(onComplete, 850);
  };

  const handleSignIn = async () => {
    if (email.trim() === '' || password === '') {
      Alert.alert(t('common.error'), 'Email + mot de passe requis.');
      return;
    }
    setSubmitting(true);
    try {
      await signIn(email.trim(), password);
      playSuccess(() => router.replace('/(tabs)/dashboard'));
    } catch (err) {
      // 403 « email non vérifié » — router vers la saisie d'OTP au lieu
      // d'une impasse (parité web owner/login).
      const response = (err as { response?: { status?: number; data?: unknown } })
        .response;
      const data = response?.data as
        | { email_verification_required?: boolean }
        | undefined;
      if (response?.status === 403 && data?.email_verification_required) {
        setSubmitting(false);
        router.push({
          pathname: '/(auth)/verify-otp',
          params: { email: email.trim() },
        });
        return;
      }
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common.error'), extractApiErrorMessage(err));
      setSubmitting(false);
    }
  };

  return (
    <YStack
      flex={1}
      backgroundColor="$background"
      paddingTop={insets.top + 36}
      paddingHorizontal="$5"
      paddingBottom={insets.bottom + 16}
      gap="$5"
    >
      <YStack gap="$3">
        <YStack
          width={56}
          height={56}
          borderRadius={16}
          backgroundColor={brand.primaryAlpha10}
          alignItems="center"
          justifyContent="center"
        >
          <Building2 size={28} color={brand.primary} />
        </YStack>
        <YStack gap="$2">
          <H1 fontSize={28} fontWeight="900">
            {t('auth.loginTitle')}
          </H1>
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
        fontWeight="800"
        borderRadius={14}
        onPress={handleSignIn}
        disabled={submitting || succeeded}
        icon={submitting && !succeeded ? <Spinner /> : undefined}
      >
        {t('auth.signIn')}
      </Button>

      <Link href={'/(auth)/forgot-password' as never} asChild>
        <Paragraph color="$brand" fontWeight="700" textAlign="center">
          {t('auth.forgotPassword')}
        </Paragraph>
      </Link>

      <SocialLoginButtons
        disabled={submitting || succeeded}
        onSuccess={() => playSuccess(() => router.replace('/(tabs)/dashboard'))}
      />

      <XStack justifyContent="center" gap="$2" marginTop="auto">
        <Paragraph color="$slate500">{t('auth.noAccount')}</Paragraph>
        <Link href="/(auth)/register" asChild>
          <Paragraph color="$brand" fontWeight="700">
            {t('auth.signUp')}
          </Paragraph>
        </Link>
      </XStack>

      {succeeded && (
        <Animated.View
          pointerEvents="none"
          style={{
            position: 'absolute',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            backgroundColor: 'rgba(255,255,255,0.96)',
            alignItems: 'center',
            justifyContent: 'center',
            opacity: overlayOpacity,
          }}
        >
          <Animated.View
            style={{
              width: 96,
              height: 96,
              borderRadius: 48,
              backgroundColor: brand.success,
              alignItems: 'center',
              justifyContent: 'center',
              transform: [{ scale: checkScale }],
              opacity: checkOpacity,
            }}
          >
            <CheckCircle2 size={56} color="white" strokeWidth={2.4} />
          </Animated.View>
        </Animated.View>
      )}
    </YStack>
  );
}
