import { LogOut, User } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { Alert } from 'react-native';
import { Button, H2, H4, Paragraph, Separator, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useSession } from '@/auth/SessionProvider';
import { useMe } from '@/hooks/useMe';
import { i18n, t } from '@/i18n';

/**
 * Account tab — split into authenticated vs guest variants:
 *
 *   Guest        : friendly hint + two CTAs (sign in / create account)
 *   Authenticated: identity card (name, email), language picker, sign-out
 *
 * Language picker is local-only for now — flipping it mutates the i18n
 * runtime instance and the next render uses the new locale. Persisting
 * the choice across launches is a future enhancement (SecureStore +
 * `expo-localization.getLocales()` override at startup).
 */
export default function AccountTab() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { isAuthenticated, signOut } = useSession();

  const { data: me } = useMe(isAuthenticated);

  const handleSignOut = () => {
    Alert.alert(
      t('account.signOut'),
      t('account.signOut') + ' ?',
      [
        { text: t('common.cancel'), style: 'cancel' },
        {
          text: t('account.signOut'),
          style: 'destructive',
          onPress: () => {
            signOut();
            router.replace('/(tabs)/home');
          },
        },
      ],
    );
  };

  return (
    <YStack
      flex={1}
      backgroundColor="$background"
      paddingTop={insets.top + 16}
      paddingHorizontal="$5"
      paddingBottom={insets.bottom + 16}
      gap="$5"
    >
      <H2>{t('account.title')}</H2>

      {isAuthenticated && me ? (
        <YStack gap="$4">
          <YStack
            paddingVertical="$4"
            paddingHorizontal="$4"
            borderRadius={16}
            backgroundColor="$slate100"
            gap="$2"
          >
            <XStack gap="$3" alignItems="center">
              <YStack
                width={48}
                height={48}
                borderRadius={24}
                backgroundColor="$brand"
                alignItems="center"
                justifyContent="center"
              >
                <User size={24} color="white" />
              </YStack>
              <YStack flex={1}>
                <H4>
                  {me.firstname} {me.lastname}
                </H4>
                <Paragraph color="$slate500" size="$3">
                  {me.email}
                </Paragraph>
              </YStack>
            </XStack>
          </YStack>

          <Separator />

          <YStack gap="$2">
            <Paragraph size="$3" color="$slate500" textTransform="uppercase">
              {t('account.language')}
            </Paragraph>
            <XStack gap="$2">
              <LanguageChip
                label="FR"
                active={i18n.locale === 'fr'}
                onPress={() => {
                  i18n.locale = 'fr';
                }}
              />
              <LanguageChip
                label="EN"
                active={i18n.locale === 'en'}
                onPress={() => {
                  i18n.locale = 'en';
                }}
              />
            </XStack>
          </YStack>

          <Separator />

          <Button
            size="$5"
            backgroundColor="$slate100"
            color="$danger"
            fontWeight="700"
            icon={LogOut}
            onPress={handleSignOut}
            accessibilityRole="button"
          >
            {t('account.signOut')}
          </Button>
        </YStack>
      ) : (
        <YStack gap="$4">
          <Paragraph color="$slate500" size="$4">
            {t('account.guestHint')}
          </Paragraph>
          <Button
            size="$5"
            backgroundColor="$brand"
            color="$brandText"
            fontWeight="700"
            onPress={() => router.push('/(auth)/login')}
          >
            {t('account.signIn')}
          </Button>
          <Button
            size="$5"
            chromeless
            color="$brand"
            fontWeight="700"
            onPress={() => router.push('/(auth)/register')}
          >
            {t('account.signUp')}
          </Button>
        </YStack>
      )}
    </YStack>
  );
}

function LanguageChip({
  label,
  active,
  onPress,
}: {
  label: string;
  active: boolean;
  onPress: () => void;
}) {
  return (
    <Button
      size="$3"
      backgroundColor={active ? '$brand' : '$slate100'}
      color={active ? '$brandText' : '$slate700'}
      borderRadius={999}
      fontWeight="700"
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ selected: active }}
      accessibilityLabel={`Définir la langue : ${label}`}
    >
      {label}
    </Button>
  );
}
