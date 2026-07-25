import { useState } from 'react';
import { Alert } from 'react-native';
import { SvgXml } from 'react-native-svg';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { extractAuthErrorMessage } from '@/api/client';
import { useSession, type SocialProvider } from '@/auth/SessionProvider';
import { ClerkSocialSignInButton } from '@/components/ClerkSocialSignInButton';
import {
  buildFallbackAuthPublicConfig,
  useAuthPublicConfig,
} from '@/hooks/useAuthPublicConfig';
import { useThemeColors } from '@/theme/useThemeColors';

const GOOGLE_SVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.28-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>`;

const FACEBOOK_SVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="#1877F2" d="M24 12c0-6.627-5.373-12-12-12S0 5.373 0 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874V12h3.328l-.532 3.47h-2.796v8.385C19.612 22.954 24 17.99 24 12z"/></svg>`;

const githubSvg = (fill: string): string =>
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="${fill}" d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>`;

interface Props {
  onSuccess: () => void;
  disabled?: boolean;
  /**
   * `stacked` (défaut) = boutons pleine largeur libellés (register).
   * `icons` = rangée de 3 boutons icône seule, centrée (login).
   */
  variant?: 'stacked' | 'icons';
}

const PROVIDER_LABELS: Record<SocialProvider, string> = {
  google: 'Google',
  facebook: 'Facebook',
  github: 'GitHub',
};

type SocialiteProvider = Exclude<SocialProvider, 'google'>;

const CLERK_SOCIAL_BUTTONS: {
  provider: SocialProvider;
  label: string;
  iconXml: string | ((mutedIcon: string) => string);
}[] = [
  { provider: 'google', label: 'Continuer avec Google', iconXml: GOOGLE_SVG },
  { provider: 'facebook', label: 'Continuer avec Facebook', iconXml: FACEBOOK_SVG },
  {
    provider: 'github',
    label: 'Continuer avec GitHub',
    iconXml: (mutedIcon) => githubSvg(mutedIcon),
  },
];

export function SocialLoginButtons({ onSuccess, disabled, variant = 'stacked' }: Props) {
  const { signInWithProvider } = useSession();
  const iconOnly = variant === 'icons';
  const colors = useThemeColors();
  const { data: authConfig, isLoading: authConfigLoading, isFetched } = useAuthPublicConfig();
  const [pending, setPending] = useState<SocialProvider | null>(null);

  const config = authConfig ?? buildFallbackAuthPublicConfig();
  const clerkEnabled = config.clerk.enabled;
  const googleMethod = config.google.method;
  const showGoogle = clerkEnabled || googleMethod !== 'unavailable';
  const showFacebook = clerkEnabled || (isFetched ? Boolean(config.socialite.facebook) : false);
  const showGithub = clerkEnabled || (isFetched ? Boolean(config.socialite.github) : false);

  const runSocialiteProvider = async (provider: SocialProvider) => {
    if (pending) {
      return;
    }
    setPending(provider);
    try {
      const { cancelled } = await signInWithProvider(provider);
      if (!cancelled) {
        onSuccess();
      }
    } catch (err) {
      Alert.alert('Connexion impossible', extractAuthErrorMessage(err));
    } finally {
      setPending(null);
    }
  };

  const visibleClerkProviders = CLERK_SOCIAL_BUTTONS.filter(({ provider }) => {
    const clerkProviders = config.clerk.oauth_providers;
    if (Array.isArray(clerkProviders) && clerkProviders.length > 0 && !clerkProviders.includes(provider)) {
      return false;
    }
    if (provider === 'google') {
      return showGoogle;
    }
    if (provider === 'facebook') {
      return showFacebook;
    }
    return showGithub;
  });

  const socialiteProviders: { key: SocialiteProvider; label: string; xml: string }[] = [];
  if (!clerkEnabled) {
    if (showFacebook) {
      socialiteProviders.push({
        key: 'facebook',
        label: 'Continuer avec Facebook',
        xml: FACEBOOK_SVG,
      });
    }
    if (showGithub) {
      socialiteProviders.push({
        key: 'github',
        label: 'Continuer avec GitHub',
        xml: githubSvg(colors.mutedIcon),
      });
    }
  }

  if (!showGoogle && socialiteProviders.length === 0 && visibleClerkProviders.length === 0) {
    return null;
  }

  const clerkBusy = authConfigLoading || pending !== null;

  const buttons = clerkEnabled ? (
    visibleClerkProviders.map(({ provider, label, iconXml }) => (
      <ClerkSocialSignInButton
        key={provider}
        provider={provider}
        label={label}
        iconXml={typeof iconXml === 'function' ? iconXml(colors.mutedIcon) : iconXml}
        onSuccess={onSuccess}
        disabled={disabled}
        busy={clerkBusy}
        iconOnly={iconOnly}
      />
    ))
  ) : (
    <>
      {showGoogle && googleMethod === 'socialite' && (
        <ProviderButton
          label="Continuer avec Google"
          a11yLabel={`Se connecter avec ${PROVIDER_LABELS.google}`}
          xml={GOOGLE_SVG}
          pending={pending === 'google'}
          disabled={disabled || authConfigLoading || (pending !== null && pending !== 'google')}
          onPress={() => void runSocialiteProvider('google')}
          iconOnly={iconOnly}
        />
      )}

      {socialiteProviders.map(({ key, label, xml }) => (
        <ProviderButton
          key={key}
          label={label}
          a11yLabel={`Se connecter avec ${PROVIDER_LABELS[key]}`}
          xml={xml}
          pending={pending === key}
          disabled={disabled || Boolean(pending && pending !== key)}
          onPress={() => void runSocialiteProvider(key)}
          iconOnly={iconOnly}
        />
      ))}
    </>
  );

  return (
    <YStack gap="$3">
      <XStack alignItems="center" gap="$3">
        <YStack flex={1} height={1} backgroundColor="$borderColor" />
        <Paragraph size="$2" color="$slate500">
          ou
        </Paragraph>
        <YStack flex={1} height={1} backgroundColor="$borderColor" />
      </XStack>

      {iconOnly ? (
        <XStack justifyContent="center" alignItems="center" gap="$4">
          {buttons}
        </XStack>
      ) : (
        buttons
      )}
    </YStack>
  );
}

function ProviderButton({
  label,
  a11yLabel,
  xml,
  pending,
  disabled,
  onPress,
  iconOnly,
}: {
  label: string;
  a11yLabel?: string;
  xml: string;
  pending: boolean;
  disabled?: boolean;
  onPress: () => void;
  iconOnly?: boolean;
}) {
  const colors = useThemeColors();

  const handlePress = () => {
    if (!disabled) {
      onPress();
    }
  };

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
        backgroundColor={colors.surface}
        opacity={disabled ? 0.5 : 1}
        pressStyle={{ opacity: 0.7 }}
        onPress={handlePress}
        accessibilityRole="button"
        accessibilityLabel={a11yLabel ?? label}
        accessibilityState={{ disabled: Boolean(disabled), busy: pending }}
        hitSlop={6}
      >
        {pending ? <Spinner /> : <SvgXml xml={xml} width={24} height={24} />}
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
      backgroundColor={colors.surface}
      opacity={disabled ? 0.5 : 1}
      pressStyle={{ opacity: 0.7 }}
      onPress={handlePress}
      accessibilityRole="button"
      accessibilityLabel={a11yLabel ?? label}
      accessibilityState={{ disabled: Boolean(disabled), busy: pending }}
    >
      {pending ? <Spinner /> : <SvgXml xml={xml} width={20} height={20} />}
      <Paragraph fontSize={15} fontWeight="600" color="$color">
        {label}
      </Paragraph>
    </XStack>
  );
}
