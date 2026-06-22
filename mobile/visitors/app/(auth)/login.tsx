import { CheckCircle2 } from '@tamagui/lucide-icons';
import * as Haptics from 'expo-haptics';
import { Link, useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Alert, Animated, Easing } from 'react-native';
import { Button, H2, Input, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
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
  const [succeeded, setSucceeded] = useState(false);

  const overlayOpacity = useRef(new Animated.Value(0)).current;
  const checkScale = useRef(new Animated.Value(0.4)).current;
  const checkOpacity = useRef(new Animated.Value(0)).current;
  const haloScale = useRef(new Animated.Value(0.6)).current;
  const haloOpacity = useRef(new Animated.Value(0)).current;
  const successTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Cleanup pending success-animation timer si le user quitte avant
  // la fin de la séquence (évite un router.replace fantôme).
  useEffect(() => {
    return () => {
      if (successTimeoutRef.current) {
        clearTimeout(successTimeoutRef.current);
        successTimeoutRef.current = null;
      }
    };
  }, []);

  const playSuccessAnimation = (onComplete: () => void) => {
    setSucceeded(true);
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    Animated.parallel([
      Animated.timing(overlayOpacity, { toValue: 1, duration: 180, useNativeDriver: true }),
      Animated.sequence([
        Animated.timing(haloScale, {
          toValue: 1.4,
          duration: 520,
          easing: Easing.out(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.timing(haloOpacity, {
          toValue: 0,
          duration: 280,
          useNativeDriver: true,
        }),
      ]),
      Animated.sequence([
        Animated.timing(haloOpacity, {
          toValue: 1,
          duration: 180,
          useNativeDriver: true,
        }),
      ]),
      Animated.spring(checkScale, {
        toValue: 1,
        damping: 12,
        stiffness: 220,
        useNativeDriver: true,
      }),
      Animated.timing(checkOpacity, {
        toValue: 1,
        duration: 220,
        useNativeDriver: true,
      }),
    ]).start();
    successTimeoutRef.current = setTimeout(() => {
      successTimeoutRef.current = null;
      onComplete();
    }, 950);
  };

  const handleSignIn = async () => {
    if (email.trim() === '' || password === '') {
      Alert.alert(t('common.error'), 'Email + mot de passe requis.');
      return;
    }
    setSubmitting(true);
    try {
      await signIn(email.trim(), password);
      playSuccessAnimation(() => {
        router.replace('/(tabs)/home');
      });
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
        disabled={submitting || succeeded}
        icon={submitting && !succeeded ? <Spinner /> : undefined}
        accessibilityRole="button"
        accessibilityState={{ disabled: submitting, busy: submitting }}
      >
        {t('auth.signIn')}
      </Button>

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

      {/* Success overlay — fades in over 180 ms with a check pop + halo */}
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
              position: 'absolute',
              width: 200,
              height: 200,
              borderRadius: 100,
              backgroundColor: `${brand.success}25`,
              transform: [{ scale: haloScale }],
              opacity: haloOpacity,
            }}
          />
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
          <Animated.View style={{ opacity: checkOpacity, marginTop: 18 }}>
            <Paragraph fontSize={18} fontWeight="800" color="$slate900">
              Bienvenue !
            </Paragraph>
          </Animated.View>
        </Animated.View>
      )}
    </YStack>
  );
}
