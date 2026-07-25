import { ArrowLeft, MailCheck } from '@tamagui/lucide-icons';
import { useAuth } from '@clerk/clerk-expo';
import { Stack, useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Alert, Pressable, TextInput } from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { exchangeClerkForSanctum, verifyClerkOtp } from '@/auth/clerkExchange';
import { useSession } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';

const OTP_LENGTH = 6;
const RESEND_COOLDOWN_SECONDS = 60;

interface Props {
  emailHint: string;
}

/**
 * OTP après inscription sociale via Clerk (owner). En pratique rarement
 * atteint : les comptes OAuth sont créés sans OTP côté backend. Conservé
 * pour parité avec l'app visitor et robustesse. Nécessite {@link OptionalClerkProvider}.
 */
export function ClerkOtpVerifyScreen({ emailHint }: Props) {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { getToken } = useAuth();
  const { setToken } = useSession();
  const [digits, setDigits] = useState<string[]>(Array(OTP_LENGTH).fill(''));
  const [submitting, setSubmitting] = useState(false);
  const [resending, setResending] = useState(false);
  const [cooldown, setCooldown] = useState(RESEND_COOLDOWN_SECONDS);
  const inputRefs = useRef<Array<TextInput | null>>([]);

  useEffect(() => {
    if (cooldown <= 0) {
      return;
    }
    const t = setTimeout(() => setCooldown((c) => c - 1), 1000);
    return () => clearTimeout(t);
  }, [cooldown]);

  const setDigit = (idx: number, value: string) => {
    if (value.length > 1) {
      const chars = value.replace(/\D/g, '').slice(0, OTP_LENGTH).split('');
      const next = Array(OTP_LENGTH).fill('') as string[];
      for (let i = 0; i < chars.length; i++) {
        next[Math.min(idx + i, OTP_LENGTH - 1)] = chars[i] ?? '';
      }
      setDigits(next);
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

  const handleVerify = async () => {
    const otp = digits.join('');
    if (otp.length < OTP_LENGTH) {
      Alert.alert('Code incomplet', `Saisissez les ${OTP_LENGTH} chiffres reçus par email.`);
      return;
    }

    setSubmitting(true);
    try {
      const clerkToken = await getToken();
      if (!clerkToken) {
        Alert.alert('Session expirée', 'Relancez la connexion.');
        router.replace('/(auth)/login' as never);
        return;
      }
      const accessToken = await verifyClerkOtp(clerkToken, otp);
      setToken(accessToken);
      router.replace('/(tabs)/dashboard');
    } catch (err) {
      Alert.alert('Code invalide', extractApiErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  };

  const handleResend = async () => {
    if (cooldown > 0 || resending) {
      return;
    }

    setResending(true);
    try {
      const clerkToken = await getToken();
      if (!clerkToken) {
        Alert.alert('Session expirée', 'Relancez la connexion depuis l’écran de connexion.');
        router.replace('/(auth)/login' as never);
        return;
      }

      const result = await exchangeClerkForSanctum(clerkToken);
      if (result.kind === 'otp_required') {
        setCooldown(RESEND_COOLDOWN_SECONDS);
        Alert.alert(
          'Code renvoyé',
          'Un nouveau code a été envoyé par KeyHome. Vérifiez votre boîte mail (onglets Principal, Promotions et Courrier indésirable).',
        );
        return;
      }

      if (result.kind === 'success') {
        setToken(result.accessToken);
        router.replace('/(tabs)/dashboard');
        return;
      }

      Alert.alert('Vérification requise', result.message);
    } catch (err) {
      Alert.alert('Erreur', extractApiErrorMessage(err));
    } finally {
      setResending(false);
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
        <Pressable
          onPress={() => router.replace('/(auth)/login' as never)}
          hitSlop={8}
          accessibilityLabel="Retour"
        >
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
          <H2 fontSize={24} fontWeight="700" textAlign="center">
            Vérifiez votre email
          </H2>
          <Paragraph fontSize={13.5} color="$slate500" textAlign="center" lineHeight={20}>
            {emailHint
              ? `KeyHome a envoyé un code à 6 chiffres à l’adresse ${emailHint}.`
              : 'KeyHome a envoyé un code à 6 chiffres à l’adresse de votre compte.'}
          </Paragraph>
          <Paragraph fontSize={12.5} color="$slate500" textAlign="center" lineHeight={18}>
            Ce n’est pas une adresse @keyhome.app — c’est normal avec la connexion sociale.
            Pensez aux spams et à l’onglet Promotions.
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
          disabled={submitting}
          onPress={() => void handleVerify()}
        >
          {submitting ? 'Vérification…' : 'Vérifier'}
        </Button>

        <Pressable
          onPress={() => void handleResend()}
          hitSlop={6}
          disabled={cooldown > 0 || resending}
        >
          <Paragraph
            fontSize={13}
            color={cooldown > 0 ? '$slate500' : brand.primary}
            textAlign="center"
            fontWeight="700"
          >
            {cooldown > 0
              ? `Renvoyer le code (${cooldown}s)`
              : resending
                ? 'Envoi…'
                : 'Renvoyer le code'}
          </Paragraph>
        </Pressable>
      </YStack>
    </>
  );
}
