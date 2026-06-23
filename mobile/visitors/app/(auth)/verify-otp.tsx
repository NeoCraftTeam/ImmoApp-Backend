import { ArrowLeft, MailCheck } from '@tamagui/lucide-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Alert, Pressable, TextInput } from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { KeyHomeLogo } from '@/components/KeyHomeLogo';
import { useResendVerification, useVerifyEmailOtp } from '@/hooks/useAuthExtras';
import { useSession } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';

const OTP_LENGTH = 6;
const RESEND_COOLDOWN_SECONDS = 60;

/**
 * Vérification OTP — 6 cases numériques, paste-friendly (un coller
 * remplit toutes les cases). Bouton "Renvoyer le code" avec cooldown
 * 60 s. Sur succès, on installe le token retourné (si présent) et on
 * envoie l'utilisateur vers le home.
 */
export default function VerifyOtpScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ email?: string }>();
  const email = params.email;
  const { setToken } = useSession();
  const [digits, setDigits] = useState<string[]>(Array(OTP_LENGTH).fill(''));
  const [cooldown, setCooldown] = useState(0);
  const inputRefs = useRef<Array<TextInput | null>>([]);
  const verify = useVerifyEmailOtp();
  const resend = useResendVerification();

  useEffect(() => {
    if (cooldown <= 0) return;
    const t = setTimeout(() => setCooldown((c) => c - 1), 1000);
    return () => clearTimeout(t);
  }, [cooldown]);

  const setDigit = (idx: number, value: string) => {
    // Paste handler — if the user pastes a 6-digit string, fill all
    // boxes from the current focus.
    if (value.length > 1) {
      const chars = value.replace(/\D/g, '').slice(0, OTP_LENGTH).split('');
      const next = Array(OTP_LENGTH).fill('') as string[];
      for (let i = 0; i < chars.length; i++) {
        next[Math.min(idx + i, OTP_LENGTH - 1)] = chars[i] ?? '';
      }
      setDigits(next);
      const focusIdx = Math.min(idx + chars.length, OTP_LENGTH - 1);
      inputRefs.current[focusIdx]?.focus();
      return;
    }
    const sanitized = value.replace(/\D/g, '').slice(0, 1);
    setDigits((d) => {
      const next = [...d];
      next[idx] = sanitized;
      return next;
    });
    if (sanitized && idx < OTP_LENGTH - 1) {
      inputRefs.current[idx + 1]?.focus();
    }
  };

  const handleKey = (idx: number, key: string) => {
    if (key === 'Backspace' && digits[idx] === '' && idx > 0) {
      inputRefs.current[idx - 1]?.focus();
    }
  };

  const handleVerify = async () => {
    const otp = digits.join('');
    if (otp.length < OTP_LENGTH) {
      Alert.alert('Code incomplet', `Saisissez les ${OTP_LENGTH} chiffres reçus par email.`);
      return;
    }
    if (!email) {
      Alert.alert('Email manquant', 'Revenez à l\'inscription puis réessayez.');
      return;
    }
    try {
      const result = await verify.mutateAsync({ email: email as string, otp });
      if (result?.token) {
        setToken(result.token);
      }
      router.replace('/(tabs)/home');
    } catch (err) {
      Alert.alert('Code invalide', extractApiErrorMessage(err));
    }
  };

  const handleResend = async () => {
    if (cooldown > 0 || !email) return;
    try {
      await resend.mutateAsync(email as string);
      setCooldown(RESEND_COOLDOWN_SECONDS);
      Alert.alert('Code renvoyé', 'Vérifiez votre boîte mail.');
    } catch (err) {
      Alert.alert('Erreur', extractApiErrorMessage(err));
    }
  };

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack
        flex={1}
        backgroundColor="$background"
        paddingTop={insets.top + 12}
        paddingHorizontal={20}
        paddingBottom={insets.bottom + 16}
        gap={20}
      >
        <Pressable onPress={() => router.back()} hitSlop={8} accessibilityLabel="Retour">
          <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
            <ArrowLeft size={18} color={brand.slate700} />
          </YStack>
        </Pressable>

        <YStack alignItems="center" gap={8}>
          <YStack
            width={70}
            height={70}
            borderRadius={35}
            backgroundColor={brand.primaryAlpha10}
            alignItems="center"
            justifyContent="center"
          >
            <MailCheck size={32} color={brand.primary} />
          </YStack>
          <KeyHomeLogo size={18} />
          <H2 fontSize={24} fontWeight="700" textAlign="center">
            Vérifiez votre email
          </H2>
          <Paragraph fontSize={13.5} color="$slate500" textAlign="center" lineHeight={20}>
            Nous avons envoyé un code à 6 chiffres
            {email ? ` à ${email}.` : '.'}
          </Paragraph>
        </YStack>

        <XStack gap={10} alignSelf="center">
          {digits.map((d, idx) => (
            <TextInput
              key={idx}
              ref={(r) => {
                inputRefs.current[idx] = r;
              }}
              value={d}
              onChangeText={(v) => setDigit(idx, v)}
              onKeyPress={(e) => handleKey(idx, e.nativeEvent.key)}
              keyboardType="number-pad"
              maxLength={idx === 0 ? OTP_LENGTH : 1}
              textContentType="oneTimeCode"
              autoComplete="sms-otp"
              style={{
                width: 44,
                height: 54,
                borderRadius: 10,
                borderWidth: 1.5,
                borderColor: d ? brand.primary : brand.slate300,
                backgroundColor: d ? brand.primaryAlpha10 : brand.slate100,
                textAlign: 'center',
                fontSize: 22,
                fontWeight: '800',
                color: brand.slate900,
              }}
            />
          ))}
        </XStack>

        <Button
          size="$5"
          backgroundColor="$brand"
          color="white"
          fontWeight="700"
          borderRadius={14}
          disabled={verify.isPending}
          onPress={handleVerify}
        >
          {verify.isPending ? 'Vérification…' : 'Vérifier'}
        </Button>

        <Pressable onPress={handleResend} hitSlop={6} disabled={cooldown > 0 || resend.isPending}>
          <Paragraph
            fontSize={13}
            color={cooldown > 0 ? '$slate500' : brand.primary}
            textAlign="center"
            fontWeight="700"
          >
            {cooldown > 0
              ? `Renvoyer le code (${cooldown}s)`
              : resend.isPending
                ? 'Envoi…'
                : 'Renvoyer le code'}
          </Paragraph>
        </Pressable>
      </YStack>
    </>
  );
}
