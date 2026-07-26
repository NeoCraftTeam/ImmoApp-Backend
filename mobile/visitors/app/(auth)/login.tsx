import { CheckCircle2 } from '@tamagui/lucide-icons';
import * as Haptics from 'expo-haptics';
import { Link, useRouter } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Alert,
  Animated,
  Easing,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  type TextInput,
} from 'react-native';
import { Button, H2, Input, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractAuthErrorMessage, parseApiErrorPayload, RESOLVED_BASE_URL } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { KeyHomeLogo } from '@/components/KeyHomeLogo';
import { PasswordInput } from '@/components/PasswordInput';
import { SocialLoginButtons } from '@/components/SocialLoginButtons';
import { useReducedMotion } from '@/hooks/useReducedMotion';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';

/** Out-quint easing (Apple-style, no overshoot) for the password reveal. */
const REVEAL_EASING = Easing.bezier(0.22, 1, 0.36, 1);

/** Loose check: an "@" with a dotted domain after it — enough to reveal. */
const looksLikeEmail = (value: string): boolean => /\S+@\S+\.\S+/.test(value.trim());

/**
 * Login screen — progressive email → password disclosure, then a POST to
 * `/auth/login` via the SessionProvider. On first render only the email
 * field + primary CTA show; the password field animates in (height +
 * opacity + slight slide) once the email looks plausible, on blur, or on
 * a first "Se connecter" tap. On success a brief check overlay plays
 * before pushing the user into the tab bar. Haptic success punctuates the
 * tactile feedback so the user *feels* the transition. All motion is
 * gated by `useReducedMotion` (reveal becomes an instant cross-fade).
 */
export default function Login() {
  const { signIn } = useSession();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const reducedMotion = useReducedMotion();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [succeeded, setSucceeded] = useState(false);
  const [passwordRevealed, setPasswordRevealed] = useState(false);
  const [displayLabel, setDisplayLabel] = useState(t('auth.signIn'));

  const overlayOpacity = useRef(new Animated.Value(0)).current;
  const checkScale = useRef(new Animated.Value(0.4)).current;
  const checkOpacity = useRef(new Animated.Value(0)).current;
  const revealAnim = useRef(new Animated.Value(0)).current;
  const labelOpacity = useRef(new Animated.Value(1)).current;
  const successTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const scrollRef = useRef<ScrollView>(null);
  const emailRef = useRef<TextInput>(null);
  const passwordRef = useRef<TextInput>(null);
  const formTopRef = useRef(0);
  const passwordRelYRef = useRef(0);

  const emailValid = looksLikeEmail(email);

  useEffect(() => {
    return () => {
      if (successTimeoutRef.current) clearTimeout(successTimeoutRef.current);
    };
  }, []);

  const scrollToPassword = useCallback(() => {
    requestAnimationFrame(() => {
      scrollRef.current?.scrollTo({
        y: Math.max(formTopRef.current + passwordRelYRef.current - 24, 0),
        animated: !reducedMotion,
      });
    });
  }, [reducedMotion]);

  const revealPassword = useCallback(
    (focus: boolean) => {
      setPasswordRevealed(true);
      if (reducedMotion) {
        revealAnim.setValue(1);
      } else {
        // Opacity + translateY are both native-drivable. Layout height is
        // left natural (the block mounts on reveal) so it is always visible.
        revealAnim.setValue(0);
        Animated.timing(revealAnim, {
          toValue: 1,
          duration: 280,
          easing: REVEAL_EASING,
          useNativeDriver: true,
        }).start();
      }
      if (focus) {
        setTimeout(
          () => {
            passwordRef.current?.focus();
            scrollToPassword();
          },
          reducedMotion ? 30 : 90,
        );
      }
    },
    [reducedMotion, revealAnim, scrollToPassword],
  );

  const playSuccess = (onComplete: () => void) => {
    setSucceeded(true);
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);

    if (reducedMotion) {
      checkScale.setValue(1);
      Animated.parallel([
        Animated.timing(overlayOpacity, { toValue: 1, duration: 180, useNativeDriver: true }),
        Animated.timing(checkOpacity, { toValue: 1, duration: 180, useNativeDriver: true }),
      ]).start();
      successTimeoutRef.current = setTimeout(onComplete, 600);
      return;
    }

    Animated.parallel([
      Animated.timing(overlayOpacity, { toValue: 1, duration: 180, useNativeDriver: true }),
      Animated.spring(checkScale, { toValue: 1, damping: 12, stiffness: 220, useNativeDriver: true }),
      Animated.timing(checkOpacity, { toValue: 1, duration: 220, useNativeDriver: true }),
    ]).start();
    successTimeoutRef.current = setTimeout(onComplete, 850);
  };

  const handleSignIn = async () => {
    if (!emailValid) {
      emailRef.current?.focus();
      return;
    }
    if (password === '') {
      passwordRef.current?.focus();
      scrollToPassword();
      return;
    }
    setSubmitting(true);
    try {
      await signIn(email.trim().toLowerCase(), password);
      playSuccess(() => router.replace('/(tabs)/home'));
    } catch (err) {
      const payload = parseApiErrorPayload(err);
      if (payload?.email_verification_required) {
        setSubmitting(false);
        router.push({
          pathname: '/(auth)/verify-otp',
          params: { email: email.trim() },
        });
        return;
      }
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common.error'), extractAuthErrorMessage(err));
      setSubmitting(false);
    }
  };

  // Button label lifecycle: "Se connecter" (email invalide) → "Continuer"
  // (email valide, mot de passe caché) → "Se connecter" (mot de passe révélé,
  // soumet). Le seul déclencheur de révélation est le tap sur "Continuer".
  const primaryLabel = passwordRevealed || !emailValid ? t('auth.signIn') : 'Continuer';
  const primaryDisabled = submitting || succeeded || (!passwordRevealed && !emailValid);

  const handlePrimaryPress = () => {
    if (!passwordRevealed) {
      revealPassword(true);
      return;
    }
    void handleSignIn();
  };

  useEffect(() => {
    if (displayLabel === primaryLabel) {
      return;
    }
    if (reducedMotion) {
      setDisplayLabel(primaryLabel);
      return;
    }
    Animated.timing(labelOpacity, {
      toValue: 0,
      duration: 120,
      easing: REVEAL_EASING,
      useNativeDriver: true,
    }).start(() => {
      setDisplayLabel(primaryLabel);
      Animated.timing(labelOpacity, {
        toValue: 1,
        duration: 160,
        easing: REVEAL_EASING,
        useNativeDriver: true,
      }).start();
    });
  }, [primaryLabel, reducedMotion, displayLabel, labelOpacity]);

  const passwordTranslateY = revealAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [8, 0],
  });

  return (
    <YStack
      flex={1}
      backgroundColor="$background"
      paddingTop={insets.top + 24}
      paddingBottom={insets.bottom + 16}
    >
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView
          ref={scrollRef}
          keyboardShouldPersistTaps="handled"
          contentContainerStyle={{
            flexGrow: 1,
            paddingHorizontal: 20,
            paddingBottom: 8,
          }}
          showsVerticalScrollIndicator={false}
        >
          <YStack gap="$5" flex={1}>
            <YStack alignItems="flex-start" gap="$4">
              <KeyHomeLogo size={22} />
              <YStack gap="$2">
                <H2>{t('auth.loginTitle')}</H2>
                <Paragraph color="$slate500" size="$4">
                  {t('auth.loginSubtitle')}
                </Paragraph>
              </YStack>
            </YStack>

            <YStack
              onLayout={(e) => {
                formTopRef.current = e.nativeEvent.layout.y;
              }}
            >
              <YStack gap="$1">
                <Paragraph size="$3" color="$slate500">
                  {t('auth.email')}
                </Paragraph>
                <Input
                  ref={emailRef}
                  value={email}
                  onChangeText={setEmail}
                  returnKeyType="done"
                  keyboardType="email-address"
                  autoCapitalize="none"
                  autoCorrect={false}
                  autoComplete="email"
                  textContentType="emailAddress"
                  placeholder="email@exemple.com"
                  size="$4"
                  accessibilityLabel={t('auth.email')}
                />
                {email.trim() !== '' && !emailValid ? (
                  <Paragraph size="$2" color={brand.danger}>
                    Entrez une adresse e-mail valide.
                  </Paragraph>
                ) : null}
              </YStack>

              {passwordRevealed ? (
                <Animated.View
                  onLayout={(e) => {
                    passwordRelYRef.current = e.nativeEvent.layout.y;
                  }}
                  style={{
                    opacity: reducedMotion ? 1 : revealAnim,
                    transform: [{ translateY: reducedMotion ? 0 : passwordTranslateY }],
                  }}
                >
                  <YStack marginTop="$3" gap="$1">
                    <Paragraph size="$3" color="$slate500">
                      {t('auth.password')}
                    </Paragraph>
                    <PasswordInput
                      ref={passwordRef}
                      value={password}
                      onChangeText={setPassword}
                      onSubmitEditing={() => void handleSignIn()}
                      returnKeyType="go"
                      autoComplete="current-password"
                      textContentType="password"
                      placeholder="••••••••"
                      size="$4"
                      accessibilityLabel={t('auth.password')}
                    />
                  </YStack>
                </Animated.View>
              ) : null}
            </YStack>

            <Button
              size="$5"
              backgroundColor="$brand"
              onPress={handlePrimaryPress}
              disabled={primaryDisabled}
              icon={submitting && !succeeded ? <Spinner /> : undefined}
              accessibilityRole="button"
              accessibilityLabel={primaryLabel}
              accessibilityState={{ disabled: primaryDisabled, busy: submitting }}
            >
              <Animated.Text
                style={{
                  opacity: labelOpacity,
                  color: brand.primaryText,
                  fontSize: 16,
                  fontWeight: '700',
                }}
              >
                {displayLabel}
              </Animated.Text>
            </Button>

            <SocialLoginButtons
              variant="icons"
              disabled={submitting || succeeded}
              onSuccess={() => router.replace('/(tabs)/home')}
            />

            {__DEV__ ? (
              <Paragraph size="$2" color="$slate500" textAlign="center">
                API : {RESOLVED_BASE_URL}
              </Paragraph>
            ) : null}

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

            <YStack flex={1} justifyContent="flex-end" minHeight={48}>
              <Button size="$3" chromeless onPress={() => router.replace('/(tabs)/home')}>
                {t('auth.continueAsGuest')}
              </Button>
            </YStack>
          </YStack>
        </ScrollView>
      </KeyboardAvoidingView>

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
