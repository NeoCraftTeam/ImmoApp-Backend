import { ArrowLeft, CheckCircle2 } from '@tamagui/lucide-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, TextInput } from 'react-native';
import { Button, H2, Paragraph, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { KeyHomeLogo } from '@/components/KeyHomeLogo';
import { useResetPassword } from '@/hooks/useAuthExtras';
import { brand } from '@/theme/tokens';

/**
 * Réinitialisation — token (collé depuis le mail) + nouveau mot de
 * passe + confirmation. Sur succès, propose de retourner à login.
 */
export default function ResetPasswordScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ email?: string; token?: string }>();
  const [email, setEmail] = useState(params.email ?? '');
  const [token, setToken] = useState(params.token ?? '');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [done, setDone] = useState(false);
  const mutation = useResetPassword();

  const handleSubmit = async () => {
    if (email.trim() === '' || token.trim() === '') {
      Alert.alert('Champs manquants', 'Email et code sont requis.');
      return;
    }
    if (password.length < 8) {
      Alert.alert('Mot de passe trop court', 'Au moins 8 caractères.');
      return;
    }
    if (password !== confirm) {
      Alert.alert('Confirmation', 'Les mots de passe ne correspondent pas.');
      return;
    }
    try {
      await mutation.mutateAsync({
        email: email.trim().toLowerCase(),
        token: token.trim(),
        password,
        password_confirmation: confirm,
      });
      setDone(true);
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

        {done ? (
          <YStack flex={1} alignItems="center" justifyContent="center" gap={14}>
            <CheckCircle2 size={56} color={brand.success} />
            <H2 fontSize={22} fontWeight="700" textAlign="center">
              Mot de passe réinitialisé
            </H2>
            <Paragraph fontSize={14} color="$slate500" textAlign="center">
              Connectez-vous avec votre nouveau mot de passe.
            </Paragraph>
            <Button
              size="$5"
              backgroundColor="$brand"
              color="white"
              fontWeight="700"
              borderRadius={14}
              marginTop={10}
              onPress={() => router.replace('/(auth)/login')}
            >
              Se connecter
            </Button>
          </YStack>
        ) : (
          <YStack gap={14}>
            <KeyHomeLogo size={22} />
            <YStack gap={6}>
              <H2 fontSize={26} fontWeight="700">Nouveau mot de passe</H2>
              <Paragraph fontSize={14} color="$slate500">
                Saisissez votre email et le code reçu dans le mail.
              </Paragraph>
            </YStack>

            <Field label="Email" value={email} onChange={setEmail} keyboardType="email-address" autoCapitalize="none" />
            <Field label="Code de réinitialisation" value={token} onChange={setToken} placeholder="Collez le code du lien reçu par email (token=…)" autoCapitalize="none" />
            <Field label="Nouveau mot de passe" value={password} onChange={setPassword} secure />
            <Field label="Confirmation" value={confirm} onChange={setConfirm} secure />

            <Button
              size="$5"
              backgroundColor="$brand"
              color="white"
              fontWeight="700"
              borderRadius={14}
              disabled={mutation.isPending}
              onPress={handleSubmit}
            >
              {mutation.isPending ? 'Mise à jour…' : 'Réinitialiser'}
            </Button>
          </YStack>
        )}
      </YStack>
    </>
  );
}

function Field({
  label,
  value,
  onChange,
  placeholder,
  secure,
  keyboardType,
  autoCapitalize,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  secure?: boolean;
  keyboardType?: 'default' | 'email-address' | 'numeric';
  autoCapitalize?: 'none' | 'sentences' | 'words' | 'characters';
}) {
  return (
    <YStack gap={6}>
      <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">
        {label}
      </Paragraph>
      <TextInput
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        placeholderTextColor={brand.slate500}
        secureTextEntry={secure}
        keyboardType={keyboardType ?? 'default'}
        autoCapitalize={autoCapitalize ?? 'sentences'}
        autoCorrect={false}
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
    </YStack>
  );
}
