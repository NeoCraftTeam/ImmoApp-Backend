import { ArrowLeft, MailCheck } from '@tamagui/lucide-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Alert, Pressable, TextInput } from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { apiClient, extractApiErrorMessage } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { ClerkOtpVerifyScreen } from '@/components/ClerkOtpVerifyScreen';
import { useResendVerification, useVerifyEmailOtp } from '@/hooks/useAuthExtras';
import { useSession } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';

const OTP_LENGTH = 6;
const RESEND_COOLDOWN_SECONDS = 60;

/**
 * Email OTP verification — 6 paste-friendly digit boxes + a 60 s resend
 * cooldown. On success we install the returned token (if any) and route
 * to the dashboard. `mode=clerk` routes to the Clerk social OTP screen.
 */
export default function VerifyOtpScreen() {
  const params = useLocalSearchParams<{ email?: string; mode?: string }>();

  if (params.mode === 'clerk') {
    return <ClerkOtpVerifyScreen emailHint={params.email ?? ''} />;
  }

  return <EmailVerifyOtpScreen initialEmail={params.email ?? ''} />;
}

function EmailVerifyOtpScreen({ initialEmail }: { initialEmail: string }) {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { setToken, isAuthenticated } = useSession();
  const [email, setEmail] = useState(initialEmail);
  const [editingEmail, setEditingEmail] = useState(false);
  const [newEmail, setNewEmail] = useState('');
  const [savingEmail, setSavingEmail] = useState(false);
  const [digits, setDigits] = useState<string[]>(Array(OTP_LENGTH).fill(''));
  const [cooldown, setCooldown] = useState(0);
  const inputRefs = useRef<Array<TextInput | null>>([]);
  const verify = useVerifyEmailOtp();
  const resend = useResendVerification();

  useEffect(() => {
    if (cooldown <= 0) return;
    const id = setTimeout(() => setCooldown((c) => c - 1), 1000);
    return () => clearTimeout(id);
  }, [cooldown]);

  const setDigit = (idx: number, value: string) => {
    if (value.length > 1) {
      const chars = value.replace(/\D/g, '').slice(0, OTP_LENGTH).split('');
      const next = Array(OTP_LENGTH).fill('') as string[];
      for (let i = 0; i < chars.length; i++) {
        next[Math.min(idx + i, OTP_LENGTH - 1)] = chars[i] ?? '';
      }
      setDigits(next);
      inputRefs.current[Math.min(idx + chars.length, OTP_LENGTH - 1)]?.focus();
      return;
    }
    const sanitized = value.replace(/\D/g, '').slice(0, 1);
    setDigits((d) => {
      const next = [...d];
      next[idx] = sanitized;
      return next;
    });
    if (sanitized && idx < OTP_LENGTH - 1) inputRefs.current[idx + 1]?.focus();
  };

  const handleKey = (idx: number, key: string) => {
    if (key === 'Backspace' && digits[idx] === '' && idx > 0) {
      inputRefs.current[idx - 1]?.focus();
    }
  };

  // Arrivée possible via `router.replace` (inscription ou login 403) —
  // sans historique, un `back()` nu ne ferait rien. Fallback explicite
  // vers l'inscription pour corriger une faute de frappe dans l'email.
  const goBackToRegister = () => {
    if (router.canGoBack()) {
      router.back();
    } else {
      router.replace('/(auth)/register' as never);
    }
  };

  // Compte créé + token en session → correction inline via
  // POST /auth/update-unverified-email ; sinon retour à l'inscription.
  const handleWrongEmail = () => {
    if (isAuthenticated) {
      setNewEmail(email);
      setEditingEmail(true);
    } else {
      goBackToRegister();
    }
  };

  const handleSaveEmail = async () => {
    const candidate = newEmail.trim().toLowerCase();
    if (candidate === '' || !candidate.includes('@')) {
      Alert.alert('Email invalide', 'Saisissez une adresse email valide.');
      return;
    }
    setSavingEmail(true);
    try {
      await apiClient.post(ENDPOINTS.auth.updateUnverifiedEmail, { email: candidate });
      setEmail(candidate);
      setEditingEmail(false);
      setDigits(Array(OTP_LENGTH).fill(''));
      setCooldown(RESEND_COOLDOWN_SECONDS);
      Alert.alert('Email corrigé', `Un nouveau code a été envoyé à ${candidate}.`);
    } catch (err) {
      Alert.alert('Erreur', extractApiErrorMessage(err));
    } finally {
      setSavingEmail(false);
    }
  };

  const handleVerify = async () => {
    const otp = digits.join('');
    if (otp.length < OTP_LENGTH) {
      Alert.alert('Code incomplet', `Saisissez les ${OTP_LENGTH} chiffres reçus par email.`);
      return;
    }
    if (!email) {
      Alert.alert('Email manquant', 'Revenez à l’inscription puis réessayez.');
      return;
    }
    try {
      const result = await verify.mutateAsync({ email: email as string, otp });
      const token = result?.token ?? result?.access_token;
      if (token) setToken(token);
      router.replace('/(tabs)/dashboard');
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
        <Pressable onPress={goBackToRegister} hitSlop={8} accessibilityLabel="Retour">
          <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
            <ArrowLeft size={18} color={brand.slate700} />
          </YStack>
        </Pressable>

        <YStack alignItems="center" gap={8}>
          <YStack width={70} height={70} borderRadius={35} backgroundColor={brand.primaryAlpha10} alignItems="center" justifyContent="center">
            <MailCheck size={32} color={brand.primary} />
          </YStack>
          <H2 fontSize={24} fontWeight="700" textAlign="center">
            Vérifiez votre email
          </H2>
          <Paragraph fontSize={13.5} color="$slate500" textAlign="center" lineHeight={20}>
            Nous avons envoyé un code à 6 chiffres{email ? ` à ${email}.` : '.'}
          </Paragraph>
          {editingEmail ? (
            <YStack gap={8} width="100%" paddingHorizontal={8}>
              <TextInput
                value={newEmail}
                onChangeText={setNewEmail}
                placeholder="nouvelle-adresse@exemple.com"
                placeholderTextColor={brand.slate500}
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
                autoFocus
                style={{
                  borderWidth: 1,
                  borderColor: brand.slate300,
                  borderRadius: 12,
                  paddingHorizontal: 14,
                  paddingVertical: 12,
                  fontSize: 15,
                  color: brand.slate900,
                }}
              />
              <XStack gap={8} justifyContent="center">
                <Pressable onPress={() => setEditingEmail(false)} hitSlop={6} accessibilityRole="button">
                  <Paragraph fontSize={13} fontWeight="700" color="$slate500" padding={8}>
                    Annuler
                  </Paragraph>
                </Pressable>
                <Pressable
                  onPress={() => void handleSaveEmail()}
                  hitSlop={6}
                  disabled={savingEmail}
                  accessibilityRole="button"
                >
                  <Paragraph fontSize={13} fontWeight="800" color={brand.primary} padding={8}>
                    {savingEmail ? 'Enregistrement…' : 'Corriger et renvoyer le code'}
                  </Paragraph>
                </Pressable>
              </XStack>
            </YStack>
          ) : (
            <Pressable onPress={handleWrongEmail} hitSlop={6} accessibilityRole="button">
              <Paragraph fontSize={13} fontWeight="700" color={brand.primary} textDecorationLine="underline">
                Ce n'est pas le bon email ? Modifier
              </Paragraph>
            </Pressable>
          )}
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

        <Button size="$5" backgroundColor="$brand" color="white" fontWeight="700" borderRadius={14} disabled={verify.isPending} onPress={handleVerify}>
          {verify.isPending ? 'Vérification…' : 'Vérifier'}
        </Button>

        <Pressable onPress={handleResend} hitSlop={6} disabled={cooldown > 0 || resend.isPending}>
          <Paragraph fontSize={13} color={cooldown > 0 ? '$slate500' : brand.primary} textAlign="center" fontWeight="700">
            {cooldown > 0 ? `Renvoyer le code (${cooldown}s)` : resend.isPending ? 'Envoi…' : 'Renvoyer le code'}
          </Paragraph>
        </Pressable>
      </YStack>
    </>
  );
}
