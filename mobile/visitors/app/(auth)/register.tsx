import { Link, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, ScrollView } from 'react-native';
import { Button, H2, Input, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { z } from 'zod';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { KeyHomeLogo } from '@/components/KeyHomeLogo';
import { t } from '@/i18n';

/**
 * Sign-up screen. Aligné sur `RegisterRequest` côté backend :
 *  - firstname / lastname : 50 max, lettres + espaces + accents
 *  - email : format valide, 255 max
 *  - phone_number : 10–15 chars, optionnel + / chiffres / espaces / tirets / ()
 *  - password : 8 min
 *  - confirm_password : doit matcher
 *
 * On valide côté client en zod pour éviter un round-trip 422 sur les
 * fautes triviales ; le backend re-valide en authority.
 */
const RegisterSchema = z
  .object({
    firstname: z
      .string()
      .trim()
      .min(1, 'Prénom requis')
      .max(50, '50 caractères maximum')
      .regex(/^[a-zA-ZÀ-ÿ\s]+$/, 'Lettres et espaces uniquement'),
    lastname: z
      .string()
      .trim()
      .min(1, 'Nom requis')
      .max(50, '50 caractères maximum')
      .regex(/^[a-zA-ZÀ-ÿ\s]+$/, 'Lettres et espaces uniquement'),
    email: z.string().trim().email('Email invalide').max(255, '255 caractères maximum'),
    phone_number: z
      .string()
      .trim()
      .regex(/^[+]?[0-9\s\-()]{10,15}$/, 'Numéro invalide (10-15 chiffres)'),
    password: z.string().min(8, 'Mot de passe : 8 caractères minimum'),
    confirm_password: z.string(),
  })
  .refine((d) => d.password === d.confirm_password, {
    message: 'Les mots de passe ne correspondent pas',
    path: ['confirm_password'],
  });

export default function Register() {
  const { signUp } = useSession();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const [form, setForm] = useState({
    firstname: '',
    lastname: '',
    email: '',
    phone_number: '',
    password: '',
    confirm_password: '',
  });
  const [errors, setErrors] = useState<Partial<Record<keyof typeof form, string>>>({});
  const [submitting, setSubmitting] = useState(false);

  const update = (k: keyof typeof form) => (v: string) => {
    setForm((f) => ({ ...f, [k]: v }));
    if (errors[k]) {
      setErrors((e) => ({ ...e, [k]: undefined }));
    }
  };

  const handleSignUp = async () => {
    const parsed = RegisterSchema.safeParse(form);
    if (!parsed.success) {
      const fieldErrors: Partial<Record<keyof typeof form, string>> = {};
      for (const issue of parsed.error.issues) {
        const k = issue.path[0] as keyof typeof form;
        if (!fieldErrors[k]) fieldErrors[k] = issue.message;
      }
      setErrors(fieldErrors);
      return;
    }

    setSubmitting(true);
    try {
      const result = await signUp(parsed.data);
      if (result.emailVerificationRequired) {
        router.replace({
          pathname: '/(auth)/verify-otp',
          params: { email: parsed.data.email },
        } as never);
      } else {
        router.replace('/(tabs)/home');
      }
    } catch (err) {
      Alert.alert(t('common.error'), extractApiErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <ScrollView
      contentContainerStyle={{
        flexGrow: 1,
        paddingTop: insets.top + 24,
        paddingHorizontal: 20,
        paddingBottom: insets.bottom + 16,
      }}
      keyboardShouldPersistTaps="handled"
    >
      <YStack gap="$4">
        <KeyHomeLogo size={22} />
        <YStack gap="$2">
          <H2>{t('auth.registerTitle')}</H2>
          <Paragraph color="$slate500" size="$4">
            {t('auth.registerSubtitle')}
          </Paragraph>
        </YStack>

        <YStack gap="$3">
          <XStack gap="$3">
            <YStack flex={1} gap="$1">
              <Paragraph size="$3" color="$slate500">
                {t('auth.firstname')}
              </Paragraph>
              <Input
                value={form.firstname}
                onChangeText={update('firstname')}
                autoCapitalize="words"
                autoComplete="given-name"
                size="$4"
                borderColor={errors.firstname ? '$danger' : undefined}
              />
              {errors.firstname && (
                <Paragraph size="$2" color="$danger">
                  {errors.firstname}
                </Paragraph>
              )}
            </YStack>
            <YStack flex={1} gap="$1">
              <Paragraph size="$3" color="$slate500">
                {t('auth.lastname')}
              </Paragraph>
              <Input
                value={form.lastname}
                onChangeText={update('lastname')}
                autoCapitalize="words"
                autoComplete="family-name"
                size="$4"
                borderColor={errors.lastname ? '$danger' : undefined}
              />
              {errors.lastname && (
                <Paragraph size="$2" color="$danger">
                  {errors.lastname}
                </Paragraph>
              )}
            </YStack>
          </XStack>

          <YStack gap="$1">
            <Paragraph size="$3" color="$slate500">
              {t('auth.email')}
            </Paragraph>
            <Input
              value={form.email}
              onChangeText={update('email')}
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
              autoComplete="email"
              textContentType="emailAddress"
              placeholder="email@exemple.com"
              size="$4"
              borderColor={errors.email ? '$danger' : undefined}
            />
            {errors.email && (
              <Paragraph size="$2" color="$danger">
                {errors.email}
              </Paragraph>
            )}
          </YStack>

          <YStack gap="$1">
            <Paragraph size="$3" color="$slate500">
              Numéro de téléphone
            </Paragraph>
            <Input
              value={form.phone_number}
              onChangeText={update('phone_number')}
              keyboardType="phone-pad"
              autoComplete="tel"
              textContentType="telephoneNumber"
              placeholder="+237 6 12 34 56 78"
              size="$4"
              borderColor={errors.phone_number ? '$danger' : undefined}
            />
            {errors.phone_number && (
              <Paragraph size="$2" color="$danger">
                {errors.phone_number}
              </Paragraph>
            )}
          </YStack>

          <YStack gap="$1">
            <Paragraph size="$3" color="$slate500">
              {t('auth.password')}
            </Paragraph>
            <Input
              value={form.password}
              onChangeText={update('password')}
              secureTextEntry
              autoCapitalize="none"
              autoComplete="new-password"
              textContentType="newPassword"
              placeholder="8 caractères minimum"
              size="$4"
              borderColor={errors.password ? '$danger' : undefined}
            />
            {errors.password && (
              <Paragraph size="$2" color="$danger">
                {errors.password}
              </Paragraph>
            )}
          </YStack>

          <YStack gap="$1">
            <Paragraph size="$3" color="$slate500">
              Confirmer le mot de passe
            </Paragraph>
            <Input
              value={form.confirm_password}
              onChangeText={update('confirm_password')}
              secureTextEntry
              autoCapitalize="none"
              autoComplete="new-password"
              textContentType="newPassword"
              placeholder="••••••••"
              size="$4"
              borderColor={errors.confirm_password ? '$danger' : undefined}
            />
            {errors.confirm_password && (
              <Paragraph size="$2" color="$danger">
                {errors.confirm_password}
              </Paragraph>
            )}
          </YStack>
        </YStack>

        <Button
          size="$5"
          backgroundColor="$brand"
          color="$brandText"
          fontWeight="700"
          onPress={handleSignUp}
          disabled={submitting}
          icon={submitting ? <Spinner /> : undefined}
          accessibilityRole="button"
          accessibilityState={{ disabled: submitting, busy: submitting }}
          marginTop={6}
        >
          {t('auth.signUp')}
        </Button>

        <XStack justifyContent="center" gap="$2">
          <Paragraph color="$slate500">{t('auth.haveAccount')}</Paragraph>
          <Link href="/(auth)/login" asChild>
            <Paragraph color="$brand" fontWeight="600">
              {t('auth.signIn')}
            </Paragraph>
          </Link>
        </XStack>
      </YStack>
    </ScrollView>
  );
}
