import { useState } from 'react';
import { Alert, useColorScheme } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { extractApiErrorMessage } from '@/api/client';
import { useSession, type SocialProvider } from '@/auth/SessionProvider';
import { ClerkSocialSignInButton } from '@/components/ClerkSocialSignInButton';
import {
  FacebookBrandIcon,
  GithubBrandIcon,
  GoogleBrandIcon,
} from '@/components/SocialBrandIcons';
import {
  buildFallbackAuthPublicConfig,
  useAuthPublicConfig,
} from '@/hooks/useAuthPublicConfig';

interface Props {
  /** Called after a successful social sign-in (navigate to dashboard). */
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

/**
 * Boutons de connexion sociale bailleur (Google / Facebook / GitHub).
 *
 * Quand Clerk est disponible (clé publique en env ou via `/config/auth`),
 * on passe par **Clerk SSO** — même flux que l'app visitors + le web
 * keyhome.app, mais l'échange Sanctum se fait avec `role=agent` (cf.
 * `clerkExchange.ts`). Sans Clerk, repli sur l'ancien flux Laravel
 * Socialite (`SessionProvider.signInWithProvider`).
 */
export function SocialLoginButtons({ onSuccess, disabled, variant = 'stacked' }: Props) {
  const { signInWithProvider } = useSession();
  const iconOnly = variant === 'icons';
  const scheme = useColorScheme();
  const { data: authConfig, isLoading: authConfigLoading, isFetched } = useAuthPublicConfig();
  const [pending, setPending] = useState<SocialProvider | null>(null);

  // GitHub monochrome : blanc en sombre, quasi-noir en clair.
  const githubColor = scheme === 'dark' ? '#FFFFFF' : '#1A1A1A';
  const SOCIAL_META: {
    key: SocialProvider;
    label: string;
    icon: React.ReactNode;
  }[] = [
    { key: 'google', label: 'Continuer avec Google', icon: <GoogleBrandIcon /> },
    { key: 'facebook', label: 'Continuer avec Facebook', icon: <FacebookBrandIcon /> },
    { key: 'github', label: 'Continuer avec GitHub', icon: <GithubBrandIcon color={githubColor} /> },
  ];

  const config = authConfig ?? buildFallbackAuthPublicConfig();
  const clerkEnabled = config.clerk.enabled;
  const googleMethod = config.google.method;
  const showGoogle = clerkEnabled || googleMethod !== 'unavailable';
  const showFacebook = clerkEnabled || (isFetched ? Boolean(config.socialite.facebook) : false);
  const showGithub = clerkEnabled || (isFetched ? Boolean(config.socialite.github) : false);

  const handleSocialite = async (provider: SocialProvider) => {
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
      Alert.alert('Connexion impossible', extractApiErrorMessage(err));
    } finally {
      setPending(null);
    }
  };

  const isVisible = (key: SocialProvider): boolean => {
    if (key === 'google') return showGoogle;
    if (key === 'facebook') return showFacebook;
    return showGithub;
  };

  const clerkProviders = config.clerk.oauth_providers;
  const visibleClerkButtons = SOCIAL_META.filter(({ key }) => {
    if (
      Array.isArray(clerkProviders)
      && clerkProviders.length > 0
      && !clerkProviders.includes(key)
    ) {
      return false;
    }
    return isVisible(key);
  });

  const visibleSocialiteButtons = SOCIAL_META.filter(({ key }) => isVisible(key));

  if (clerkEnabled) {
    if (visibleClerkButtons.length === 0) {
      return null;
    }
  } else if (visibleSocialiteButtons.length === 0) {
    return null;
  }

  const clerkBusy = authConfigLoading || pending !== null;

  const buttons = clerkEnabled
    ? visibleClerkButtons.map(({ key, label, icon }) => (
        <ClerkSocialSignInButton
          key={key}
          provider={key}
          label={label}
          icon={icon}
          onSuccess={onSuccess}
          disabled={disabled}
          busy={clerkBusy}
          iconOnly={iconOnly}
        />
      ))
    : visibleSocialiteButtons.map(({ key, label, icon }) => {
        const a11yLabel = `Se connecter avec ${PROVIDER_LABELS[key]}`;
        const btnDisabled = disabled || Boolean(pending && pending !== key);
        const handlePress = () => {
          if (!disabled) {
            void handleSocialite(key);
          }
        };

        if (iconOnly) {
          return (
            <XStack
              key={key}
              width={56}
              height={56}
              alignItems="center"
              justifyContent="center"
              borderRadius={16}
              borderWidth={1}
              borderColor="$borderColor"
              backgroundColor="$background"
              opacity={btnDisabled ? 0.5 : 1}
              pressStyle={{ opacity: 0.7 }}
              onPress={handlePress}
              accessibilityRole="button"
              accessibilityLabel={a11yLabel}
              accessibilityState={{ disabled: Boolean(disabled), busy: pending === key }}
              hitSlop={6}
            >
              {pending === key ? <Spinner /> : icon}
            </XStack>
          );
        }

        return (
          <XStack
            key={key}
            alignItems="center"
            justifyContent="center"
            gap="$3"
            height={50}
            borderRadius={12}
            borderWidth={1}
            borderColor="$borderColor"
            backgroundColor="$background"
            opacity={btnDisabled ? 0.5 : 1}
            pressStyle={{ opacity: 0.7 }}
            onPress={handlePress}
            accessibilityRole="button"
            accessibilityLabel={label}
            accessibilityState={{ disabled: Boolean(disabled), busy: pending === key }}
          >
            {pending === key ? <Spinner /> : icon}
            <Paragraph fontSize={15} fontWeight="600" color="$slate900">
              {label}
            </Paragraph>
          </XStack>
        );
      });

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
