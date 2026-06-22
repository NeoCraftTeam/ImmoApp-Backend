import { ArrowLeft, Mail } from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, TextInput } from 'react-native';
import { Button, H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import { useForgotPassword } from '@/hooks/useAuthExtras';
import { brand } from '@/theme/tokens';

/**
 * Mot de passe oublié — collect l'email, déclenche l'envoi du lien de
 * réinitialisation, affiche un état de confirmation puis renvoie vers
 * la page reset-password (où l'utilisateur colle le token reçu).
 */
export default function ForgotPasswordScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const mutation = useForgotPassword();

  const handleSubmit = async () => {
    if (email.trim() === '') {
      Alert.alert('Email requis', 'Saisissez votre adresse email.');
      return;
    }
    try {
      await mutation.mutateAsync(email.trim().toLowerCase());
      setSent(true);
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

        <YStack gap={6}>
          <H2 fontSize={26} fontWeight="700">Mot de passe oublié</H2>
          <Paragraph fontSize={14} color="$slate500">
            Indiquez votre email — nous vous envoyons un lien pour créer un nouveau mot de passe.
          </Paragraph>
        </YStack>

        {sent ? (
          <YStack
            padding={16}
            borderRadius={14}
            backgroundColor={`${brand.success}15`}
            gap={8}
          >
            <XStack alignItems="center" gap={8}>
              <Mail size={18} color={brand.success} />
              <Paragraph fontSize={15} fontWeight="700" color={brand.success}>
                Lien envoyé !
              </Paragraph>
            </XStack>
            <Paragraph fontSize={13} color="$slate700" lineHeight={20}>
              Vérifiez votre boîte mail et touchez le lien reçu. Une fois sur cet appareil, collez le code dans l'écran "Réinitialiser le mot de passe".
            </Paragraph>
            <Button
              size="$4"
              backgroundColor="$brand"
              color="white"
              fontWeight="700"
              marginTop={4}
              onPress={() => router.push({ pathname: '/(auth)/reset-password', params: { email } } as never)}
            >
              J'ai reçu le code
            </Button>
          </YStack>
        ) : (
          <YStack gap={14}>
            <YStack gap={6}>
              <Paragraph fontSize={12} fontWeight="700" color="$slate500" textTransform="uppercase">
                Email
              </Paragraph>
              <TextInput
                value={email}
                onChangeText={setEmail}
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
                autoComplete="email"
                placeholder="email@exemple.com"
                placeholderTextColor={brand.slate500}
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
            <Button
              size="$5"
              backgroundColor="$brand"
              color="white"
              fontWeight="700"
              borderRadius={14}
              disabled={mutation.isPending}
              onPress={handleSubmit}
            >
              {mutation.isPending ? 'Envoi…' : 'Envoyer le lien'}
            </Button>
            <Pressable onPress={() => router.push('/(auth)/login')} hitSlop={6}>
              <Paragraph fontSize={13} color="$slate500" textAlign="center" textDecorationLine="underline">
                Retour à la connexion
              </Paragraph>
            </Pressable>
          </YStack>
        )}
      </YStack>
    </>
  );
}
