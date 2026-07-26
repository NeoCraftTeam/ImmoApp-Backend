import { useAuth, useSSO } from '@clerk/clerk-expo';
import * as Linking from 'expo-linking';
import { useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert } from 'react-native';
import { Paragraph, Spinner, XStack } from 'tamagui';

import { setBearerToken } from '@/api/client';
import { extractAuthErrorMessage } from '@/api/extract-error';
import {
  formatClerkAuthError,
  isClerkAlreadySignedInError,
} from '@/lib/clerk-auth-errors';
import { exchangeClerkForSanctum } from '@/auth/clerkExchange';
import { useSession } from '@/auth/SessionProvider';
import { trackEvent } from '@/services/monitoring';

export type ClerkOAuthStrategy = 'oauth_google' | 'oauth_facebook' | 'oauth_github';

export type ClerkSocialProvider = 'google' | 'facebook' | 'github';

const STRATEGY_BY_PROVIDER: Record<ClerkSocialProvider, ClerkOAuthStrategy> = {
  google: 'oauth_google',
  facebook: 'oauth_facebook',
  github: 'oauth_github',
};

interface Props {
  provider: ClerkSocialProvider;
  label: string;
  icon: React.ReactNode;
  onSuccess: () => void;
  disabled?: boolean;
  busy?: boolean;
  /** Compact icon-only rendering (single row of round buttons on login). */
  iconOnly?: boolean;
}

/**
 * Social sign-in via Clerk SSO — **owner / bailleur** app. Same stack as
 * keyhome.app web + the visitor app, but the Sanctum exchange runs with
 * `role=agent` (see {@link exchangeClerkForSanctum}). Must render inside
 * {@link OptionalClerkProvider}. Icons are React nodes (lucide / monogram)
 * — this workspace has no `react-native-svg`.
 */
export function ClerkSocialSignInButton({
  provider,
  label,
  icon,
  onSuccess,
  disabled,
  busy,
  iconOnly,
}: Props) {
  const router = useRouter();
  const { setToken } = useSession();
  const { getToken, signOut: clerkSignOut, isSignedIn } = useAuth();
  const { startSSOFlow } = useSSO();
  const [pending, setPending] = useState(false);

  const handlePress = useCallback(async () => {
    if (pending || disabled || busy) {
      return;
    }

    setPending(true);
    try {
      await runClerkSocialSignIn({
        provider,
        isSignedIn: Boolean(isSignedIn),
        clerkSignOut,
        getToken,
        startSSOFlow,
        onOtpRequired: (emailHint) => {
          router.push({
            pathname: '/(auth)/verify-otp',
            params: {
              mode: 'clerk',
              email: emailHint ?? '',
            },
          } as never);
        },
        onEmailVerificationRequired: (email, message) => {
          Alert.alert('Vérification requise', message);
          router.push({
            pathname: '/(auth)/verify-otp',
            params: { email },
          } as never);
        },
        onSanctumSuccess: (accessToken) => {
          setBearerToken(accessToken);
          setToken(accessToken);
          trackEvent('auth.signInSocial', { provider, via: 'clerk' });
        },
        onComplete: onSuccess,
      });
    } catch (err) {
      const message =
        err && typeof err === 'object' && 'response' in err
          ? extractAuthErrorMessage(err)
          : formatClerkAuthError(err, provider);
      Alert.alert(`Connexion ${providerLabel(provider)}`, message);
    } finally {
      setPending(false);
    }
  }, [
    busy,
    clerkSignOut,
    disabled,
    getToken,
    isSignedIn,
    onSuccess,
    pending,
    provider,
    router,
    setToken,
    startSSOFlow,
  ]);

  const isBusy = pending || Boolean(busy);

  if (iconOnly) {
    return (
      <XStack
        width={56}
        height={56}
        alignItems="center"
        justifyContent="center"
        borderRadius={16}
        borderWidth={1}
        borderColor="$borderColor"
        backgroundColor="$background"
        opacity={disabled || (busy && !pending) ? 0.5 : 1}
        pressStyle={{ opacity: 0.7 }}
        onPress={() => {
          void handlePress();
        }}
        accessibilityRole="button"
        accessibilityLabel={`Se connecter avec ${providerLabel(provider)}`}
        accessibilityState={{ disabled: Boolean(disabled), busy: isBusy }}
        hitSlop={6}
      >
        {isBusy ? <Spinner /> : icon}
      </XStack>
    );
  }

  return (
    <XStack
      alignItems="center"
      justifyContent="center"
      gap="$3"
      height={50}
      borderRadius={12}
      borderWidth={1}
      borderColor="$borderColor"
      backgroundColor="$background"
      opacity={disabled || (busy && !pending) ? 0.5 : 1}
      pressStyle={{ opacity: 0.7 }}
      onPress={() => {
        void handlePress();
      }}
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ disabled: Boolean(disabled), busy: isBusy }}
    >
      {isBusy ? <Spinner /> : icon}
      <Paragraph fontSize={15} fontWeight="600" color="$slate900">
        {label}
      </Paragraph>
    </XStack>
  );
}

async function runClerkSocialSignIn(options: {
  provider: ClerkSocialProvider;
  isSignedIn: boolean;
  clerkSignOut: (() => Promise<void>) | undefined;
  getToken: () => Promise<string | null>;
  startSSOFlow: ReturnType<typeof useSSO>['startSSOFlow'];
  onOtpRequired: (emailHint: string | null) => void;
  onEmailVerificationRequired: (email: string, message: string) => void;
  onSanctumSuccess: (accessToken: string) => void;
  onComplete: () => void;
}): Promise<void> {
  const {
    provider,
    isSignedIn,
    clerkSignOut,
    getToken,
    startSSOFlow,
    onOtpRequired,
    onEmailVerificationRequired,
    onSanctumSuccess,
    onComplete,
  } = options;

  // Session Clerk résiduelle (SSO interrompu, OTP non validé, etc.) bloque startSSOFlow.
  if (isSignedIn) {
    await clerkSignOut?.();
  }

  const redirectUrl = Linking.createURL('auth/callback');
  const strategy = STRATEGY_BY_PROVIDER[provider];

  let createdSessionId: string | null | undefined;
  let setActive: ((params: { session: string }) => Promise<void>) | undefined;
  let authSessionResult: Awaited<ReturnType<typeof startSSOFlow>>['authSessionResult'];

  try {
    ({ createdSessionId, setActive, authSessionResult } = await startSSOFlow({
      strategy,
      redirectUrl,
    }));
  } catch (err) {
    if (!isClerkAlreadySignedInError(err)) {
      throw err;
    }
    await clerkSignOut?.();
    ({ createdSessionId, setActive, authSessionResult } = await startSSOFlow({
      strategy,
      redirectUrl,
    }));
  }

  if (
    authSessionResult?.type === 'cancel'
    || authSessionResult?.type === 'dismiss'
  ) {
    return;
  }

  if (createdSessionId && setActive) {
    await setActive({ session: createdSessionId });
  }

  const clerkToken = await getToken();
  if (!clerkToken) {
    throw new Error(`Session ${provider} introuvable. Réessayez.`);
  }

  const result = await exchangeClerkForSanctum(clerkToken);

  if (result.kind === 'otp_required') {
    onOtpRequired(result.emailHint);
    return;
  }

  if (result.kind === 'email_verification_required') {
    onEmailVerificationRequired(result.email, result.message);
    return;
  }

  onSanctumSuccess(result.accessToken);
  await clerkSignOut?.();
  onComplete();
}

function providerLabel(provider: ClerkSocialProvider): string {
  switch (provider) {
    case 'google':
      return 'Google';
    case 'facebook':
      return 'Facebook';
    case 'github':
      return 'GitHub';
  }
}
