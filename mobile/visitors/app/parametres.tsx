import {
  ArrowLeft,
  Bell,
  ChevronRight,
  CreditCard,
  HelpCircle,
  Lock,
  LogOut,
  Moon,
  Shield,
  Sun,
  Trash2,
  User,
} from '@tamagui/lucide-icons';
import { Stack, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, Switch } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useSession } from '@/auth/SessionProvider';
import { useAppTheme, type ThemeMode } from '@/providers/ThemeProvider';
import { brand } from '@/theme/tokens';

const THEME_LABELS: Record<ThemeMode, string> = {
  system: 'Système',
  light: 'Clair',
  dark: 'Sombre',
};

/**
 * Settings screen — split into account / preferences / support /
 * danger sections, mirroring the web `parametres` page. Theme +
 * notification toggles are live, the rest are router pushes into
 * dedicated screens.
 */
export default function Settings() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated, signOut } = useSession();
  const { mode, scheme, setMode } = useAppTheme();
  const [pushEnabled, setPushEnabled] = useState(true);
  const [emailEnabled, setEmailEnabled] = useState(true);

  const handleThemePress = () => {
    Alert.alert('Apparence', 'Choisissez le thème de l’application.', [
      { text: THEME_LABELS.system, onPress: () => setMode('system') },
      { text: THEME_LABELS.light, onPress: () => setMode('light') },
      { text: THEME_LABELS.dark, onPress: () => setMode('dark') },
      { text: 'Annuler', style: 'cancel' },
    ]);
  };

  const handleLogout = () => {
    Alert.alert('Déconnexion', 'Êtes-vous sûr de vouloir vous déconnecter ?', [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Se déconnecter',
        style: 'destructive',
        onPress: async () => {
          await signOut();
          router.replace('/(tabs)/home');
        },
      },
    ]);
  };

  const handleDeleteAccount = () => {
    Alert.alert(
      'Supprimer le compte',
      'Cette action est irréversible. Vos annonces favorites et messages seront effacés.',
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Supprimer',
          style: 'destructive',
          onPress: () =>
            Alert.alert(
              'Contactez-nous',
              'Pour supprimer définitivement votre compte, écrivez à support@keyhome.app.',
            ),
        },
      ],
    );
  };

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$background">
        <XStack
          paddingTop={insets.top + 8}
          paddingHorizontal={14}
          paddingBottom={10}
          alignItems="center"
          gap={10}
          borderBottomWidth={1}
          borderBottomColor="$slate300"
        >
          <Pressable onPress={() => router.back()} hitSlop={8} accessibilityRole="button" accessibilityLabel="Retour">
            <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
              <ArrowLeft size={18} color="$slate700" />
            </YStack>
          </Pressable>
          <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
            Paramètres
          </H2>
        </XStack>

        <YStack flex={1} paddingHorizontal={16} paddingTop={18} gap={18}>
          <Section title="Compte">
            <Row
              icon={<User size={18} color="$slate700" />}
              label="Profil"
              hint={isAuthenticated ? 'Modifier vos informations' : 'Connectez-vous pour modifier'}
              onPress={() => router.push(isAuthenticated ? '/profile' : '/(auth)/login')}
            />
            <Row
              icon={<Lock size={18} color="$slate700" />}
              label="Sécurité"
              hint="Mot de passe et authentification"
              onPress={() =>
                Alert.alert(
                  'Sécurité',
                  'La modification du mot de passe arrive prochainement.',
                )
              }
            />
            <Row
              icon={<CreditCard size={18} color="$slate700" />}
              label="Crédits & paiements"
              onPress={() => router.push('/credits')}
            />
          </Section>

          <Section title="Préférences">
            <ToggleRow
              icon={<Bell size={18} color="$slate700" />}
              label="Notifications push"
              value={pushEnabled}
              onChange={setPushEnabled}
            />
            <ToggleRow
              icon={<Bell size={18} color="$slate700" />}
              label="Notifications email"
              value={emailEnabled}
              onChange={setEmailEnabled}
            />
            <Row
              icon={
                scheme === 'dark' ? (
                  <Moon size={18} color="$slate700" />
                ) : (
                  <Sun size={18} color="$slate700" />
                )
              }
              label="Apparence"
              hint={mode === 'system' ? `${THEME_LABELS.system} (${scheme === 'dark' ? 'sombre' : 'clair'})` : THEME_LABELS[mode]}
              onPress={handleThemePress}
            />
          </Section>

          <Section title="Aide et confidentialité">
            <Row
              icon={<HelpCircle size={18} color="$slate700" />}
              label="Centre d'aide"
              onPress={() => router.push('/aide')}
            />
            <Row
              icon={<Shield size={18} color="$slate700" />}
              label="Confidentialité"
              onPress={() =>
                Alert.alert(
                  'Confidentialité',
                  'Politique disponible sur keyhome.app/confidentialite.',
                )
              }
            />
          </Section>

          {isAuthenticated && (
            <YStack gap={8}>
              <Pressable onPress={handleLogout}>
                <XStack
                  alignItems="center"
                  gap={10}
                  padding={14}
                  borderRadius={12}
                  borderWidth={1}
                  borderColor="$slate300"
                >
                  <LogOut size={18} color="$slate700" />
                  <Paragraph fontSize={15} fontWeight="700" color="$slate900" flex={1}>
                    Se déconnecter
                  </Paragraph>
                </XStack>
              </Pressable>

              <Pressable onPress={handleDeleteAccount}>
                <XStack
                  alignItems="center"
                  gap={10}
                  padding={14}
                  borderRadius={12}
                  borderWidth={1}
                  borderColor={brand.danger}
                >
                  <Trash2 size={18} color={brand.danger} />
                  <Paragraph fontSize={15} fontWeight="700" color={brand.danger} flex={1}>
                    Supprimer le compte
                  </Paragraph>
                </XStack>
              </Pressable>
            </YStack>
          )}
        </YStack>
      </YStack>
    </>
  );
}

function Section({
  title,
  children,
}: {
  title: string;
  children: React.ReactNode;
}) {
  return (
    <YStack gap={8}>
      <Paragraph fontSize={11} fontWeight="800" color="$slate500" textTransform="uppercase">
        {title}
      </Paragraph>
      <YStack borderRadius={12} overflow="hidden" borderWidth={1} borderColor="$slate300">
        {children}
      </YStack>
    </YStack>
  );
}

function Row({
  icon,
  label,
  hint,
  onPress,
}: {
  icon: React.ReactNode;
  label: string;
  hint?: string;
  onPress?: () => void;
}) {
  const Content = (
    <XStack
      alignItems="center"
      gap={12}
      paddingHorizontal={14}
      paddingVertical={13}
      backgroundColor="$background"
    >
      {icon}
      <YStack flex={1} gap={1}>
        <Paragraph fontSize={14.5} fontWeight="600" color="$slate900">
          {label}
        </Paragraph>
        {hint && (
          <Paragraph fontSize={12} color="$slate500">
            {hint}
          </Paragraph>
        )}
      </YStack>
      <ChevronRight size={16} color="$slate500" />
    </XStack>
  );
  if (onPress) {
    return (
      <Pressable onPress={onPress}>
        {Content}
      </Pressable>
    );
  }
  return Content;
}

function ToggleRow({
  icon,
  label,
  value,
  onChange,
}: {
  icon: React.ReactNode;
  label: string;
  value: boolean;
  onChange: (v: boolean) => void;
}) {
  return (
    <XStack
      alignItems="center"
      gap={12}
      paddingHorizontal={14}
      paddingVertical={11}
      backgroundColor="$background"
    >
      {icon}
      <Paragraph fontSize={14.5} fontWeight="600" color="$slate900" flex={1}>
        {label}
      </Paragraph>
      <Switch
        value={value}
        onValueChange={onChange}
        trackColor={{ true: brand.primary, false: brand.slate300 }}
      />
    </XStack>
  );
}
