import { Link, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, ScrollView } from 'react-native';
import { Button, H1, Input, Paragraph, Spinner, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { z } from 'zod';

import { extractApiErrorMessage } from '@/api/client';
import { useSession } from '@/auth/SessionProvider';
import { PasswordInput } from '@/components/PasswordInput';
import { PhoneInput } from '@/components/PhoneInput';
import { t } from '@/i18n';

/**
 * Owner sign-up — posts to `/auth/registerAgent` (role = agent). Client-
 * validated with zod to avoid trivial 422 round-trips; the backend
 * re-validates as the authority.
 */
const RegisterSchema = z
  .object({
    firstname: z
      .string()
      .trim()
      .min(1, 'Prénom requis')
      .max(50, '50 caractères maximum')
      .regex(/^[a-zA-ZÀ-ÿ\s'-]+$/, 'Lettres et espaces uniquement'),
    lastname: z
      .string()
      .trim()
      .min(1, 'Nom requis')
      .max(50, '50 caractères maximum')
      .regex(/^[a-zA-ZÀ-ÿ\s'-]+$/, 'Lettres et espaces uniquement'),
    email: z.string().trim().toLowerCase().email('Email invalide').max(255),
    phone_number: z
      .string()
      .trim()
      .regex(/^[+]?[0-9\s\-()]{8,15}$/, 'Numéro invalide'),
    // Aligné sur le backend : Password::min(8)->mixedCase()->numbers()->symbols().
    password: z
      .string()
      .min(8, 'Mot de passe : 8 caractères minimum')
      .regex(/[a-z]/, 'Au moins une minuscule')
      .regex(/[A-Z]/, 'Au moins une majuscule')
      .regex(/[0-9]/, 'Au moins un chiffre')
      .regex(/[^A-Za-z0-9]/, 'Au moins un symbole'),
    password_confirmation: z.string(),
  })
  .refine((d) => d.password === d.password_confirmation, {
    message: 'Les mots de passe ne correspondent pas',
    path: ['password_confirmation'],
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
    password_confirmation: '',
  });
  const [errors, setErrors] = useState<Partial<Record<keyof typeof form, string>>>({});
  const [submitting, setSubmitting] = useState(false);

  const update = (k: keyof typeof form) => (v: string) => {
    setForm((f) => ({ ...f, [k]: v }));
    if (errors[k]) setErrors((e) => ({ ...e, [k]: undefined }));
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
      // Le backend attend `confirm_password` (pas `password_confirmation`).
      const { password_confirmation, ...rest } = parsed.data;
      const result = await signUp({ ...rest, confirm_password: password_confirmation });
      if (result.emailVerificationRequired) {
        router.replace({
          pathname: '/(auth)/verify-otp',
          params: { email: parsed.data.email },
        } as never);
      } else {
        router.replace('/(tabs)/dashboard');
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
        <YStack gap="$2">
          <H1 fontSize={26} fontWeight="900">
            {t('auth.registerTitle')}
          </H1>
          <Paragraph color="$slate500" size="$4">
            {t('auth.registerSubtitle')}
          </Paragraph>
        </YStack>

        <YStack gap="$3">
          <XStack gap="$3">
            <Field flex label={t('auth.firstname')} value={form.firstname} onChange={update('firstname')} error={errors.firstname} autoComplete="given-name" autoCapitalize="words" />
            <Field flex label={t('auth.lastname')} value={form.lastname} onChange={update('lastname')} error={errors.lastname} autoComplete="family-name" autoCapitalize="words" />
          </XStack>
          <Field label={t('auth.email')} value={form.email} onChange={update('email')} error={errors.email} keyboardType="email-address" autoComplete="email" autoCapitalize="none" placeholder="email@exemple.com" />
          <YStack gap="$1">
            <Paragraph size="$3" color="$slate500">
              {t('auth.phone')}
            </Paragraph>
            <PhoneInput
              value={form.phone_number}
              onChange={(v) => update('phone_number')(v)}
              hasError={Boolean(errors.phone_number)}
            />
            {errors.phone_number ? (
              <Paragraph size="$2" color="$danger">
                {errors.phone_number}
              </Paragraph>
            ) : null}
          </YStack>
          <Field label={t('auth.password')} value={form.password} onChange={update('password')} error={errors.password} secure autoComplete="new-password" placeholder="8 caractères minimum" />
          <Field label="Confirmer le mot de passe" value={form.password_confirmation} onChange={update('password_confirmation')} error={errors.password_confirmation} secure autoComplete="new-password" placeholder="••••••••" />
        </YStack>

        <Button
          size="$5"
          backgroundColor="$brand"
          color="$brandText"
          fontWeight="800"
          borderRadius={14}
          marginTop={4}
          onPress={handleSignUp}
          disabled={submitting}
          icon={submitting ? <Spinner /> : undefined}
        >
          {t('auth.signUp')}
        </Button>

        <XStack justifyContent="center" gap="$2">
          <Paragraph color="$slate500">{t('auth.haveAccount')}</Paragraph>
          <Link href="/(auth)/login" asChild>
            <Paragraph color="$brand" fontWeight="700">
              {t('auth.signIn')}
            </Paragraph>
          </Link>
        </XStack>
      </YStack>
    </ScrollView>
  );
}

function Field({
  label,
  value,
  onChange,
  error,
  secure,
  keyboardType,
  autoComplete,
  autoCapitalize,
  placeholder,
  flex,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  error?: string;
  secure?: boolean;
  keyboardType?: 'default' | 'email-address' | 'phone-pad';
  autoComplete?: 'given-name' | 'family-name' | 'email' | 'tel' | 'new-password';
  autoCapitalize?: 'none' | 'words';
  placeholder?: string;
  flex?: boolean;
}) {
  return (
    <YStack flex={flex ? 1 : undefined} gap="$1">
      <Paragraph size="$3" color="$slate500">
        {label}
      </Paragraph>
      {secure ? (
        <PasswordInput
          value={value}
          onChangeText={onChange}
          autoComplete={autoComplete}
          placeholder={placeholder}
          size="$4"
          borderColor={error ? '$danger' : undefined}
        />
      ) : (
        <Input
          value={value}
          onChangeText={onChange}
          keyboardType={keyboardType}
          autoComplete={autoComplete}
          autoCapitalize={autoCapitalize}
          autoCorrect={false}
          placeholder={placeholder}
          size="$4"
          borderColor={error ? '$danger' : undefined}
        />
      )}
      {error ? (
        <Paragraph size="$2" color="$danger">
          {error}
        </Paragraph>
      ) : null}
    </YStack>
  );
}
