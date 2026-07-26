import {
  AlertTriangle,
  Bell,
  Calculator,
  Calendar,
  ChevronRight,
  ClipboardList,
  CreditCard,
  GitCompareArrows,
  Heart,
  HelpCircle,
  LogIn,
  LogOut,
  Mail,
  MapPin,
  MessageCircle,
  Search,
  Settings,
  User,
} from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useEffect } from 'react';
import { Alert, Pressable, ScrollView } from 'react-native';
import { Button, H2, H4, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { KeyHomeRefreshControl } from '@/components/KeyHomeRefreshControl';
import { useSession } from '@/auth/SessionProvider';
import { useCreditsBalance } from '@/hooks/usePayments';
import { useMe } from '@/hooks/useMe';
import { useUnreadNotificationCount } from '@/hooks/useNotifications';
import { useThemeColors } from '@/theme/useThemeColors';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';

/**
 * Account hub — the visitor's home for everything that isn't browsing.
 * Sections: identity card → activity (messages, favorites, alerts,
 * notifications, comparator) → wallet → tools (estimator) → settings →
 * sign-out. Guest variant collapses to a single "Sign in" CTA with the
 * tools section still available below.
 */
export default function AccountTab() {
  const insets = useSafeAreaInsets();
  const colors = useThemeColors();
  const router = useRouter();
  const { isAuthenticated, signOut } = useSession();
  const me = useMe(isAuthenticated);
  const unread = useUnreadNotificationCount();
  const balance = useCreditsBalance();

  const isRefreshing =
    me.isRefetching || balance.isRefetching || unread.isRefetching;
  const refreshAccount = () => {
    if (isAuthenticated) {
      void me.refetch();
      void balance.refetch();
      void unread.refetch();
    }
  };

  // Si /me échoue avec 401 (token vraiment invalide) ou 404 (user
  // supprimé), on déconnecte automatiquement pour basculer en mode
  // invité plutôt que de rester bloqué sur "Chargement…".
  // Les 5xx / réseau sont retentés silencieusement par TanStack et ne
  // doivent PAS déconnecter — c'est la différence critique avec un
  // bug de production où un timeout efface la session.
  useEffect(() => {
    if (!isAuthenticated || !me.isError || me.isLoading) return;
    const status =
      (me.error as undefined | { response?: { status?: number } })?.response?.status;
    if (status === 401 || status === 404) {
      signOut();
    }
  }, [isAuthenticated, me.isError, me.isLoading, me.error, signOut]);

  // Mode invité effectif : pas de token OU /me a échoué irrécupérablement.
  const isGuest =
    !isAuthenticated ||
    (me.isError &&
      [401, 404].includes(
        ((me.error as undefined | { response?: { status?: number } })?.response?.status ?? 0),
      ));
  const meData = me.data;

  // 403 « email non vérifié » : le compte existe mais l'OTP n'a jamais
  // été validé (ex. faute de frappe dans l'email à l'inscription). On
  // affiche une carte actionnable au lieu d'un « Chargement… » sans issue.
  const meErrorData = (
    me.error as undefined | {
      response?: {
        status?: number;
        data?: { email_verification_required?: boolean; email?: string; user_id?: string };
      };
    }
  )?.response;
  const unverified =
    isAuthenticated &&
    me.isError &&
    meErrorData?.status === 403 &&
    Boolean(meErrorData.data?.email_verification_required);

  const handleSignOut = () => {
    Alert.alert(t('account.signOut'), `${t('account.signOut')} ?`, [
      { text: t('common.cancel'), style: 'cancel' },
      {
        text: t('account.signOut'),
        style: 'destructive',
        onPress: () => {
          signOut();
          router.replace('/(tabs)/home');
        },
      },
    ]);
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScrollView
        contentContainerStyle={{
          paddingTop: insets.top + 18,
          paddingHorizontal: 16,
          paddingBottom: insets.bottom + 16,
          gap: 18,
        }}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <KeyHomeRefreshControl refreshing={isRefreshing} onRefresh={refreshAccount} />
        }
      >
        <H2>{t('account.title')}</H2>

        {unverified ? (
          <YStack
            padding={16}
            borderRadius={16}
            backgroundColor={brand.primaryAlpha10}
            borderWidth={1}
            borderColor={brand.primaryAlpha20}
            gap={10}
          >
            <Paragraph fontSize={15} fontWeight="800" color="$slate900">
              Vérifiez votre email
            </Paragraph>
            <Paragraph fontSize={13} color="$slate700" lineHeight={19}>
              Votre compte{meErrorData?.data?.email ? ` (${meErrorData.data.email})` : ''} n'est
              pas encore vérifié. Saisissez le code reçu — ou corrigez l'adresse si
              vous avez fait une faute de frappe.
            </Paragraph>
            <Pressable
              onPress={() =>
                router.push({
                  pathname: '/(auth)/verify-otp',
                  params: {
                    email: meErrorData?.data?.email ?? '',
                    user_id: meErrorData?.data?.user_id ?? '',
                  },
                } as never)
              }
              accessibilityRole="button"
            >
              <YStack
                paddingVertical={11}
                borderRadius={12}
                backgroundColor={brand.primary}
                alignItems="center"
              >
                <Paragraph fontSize={14} fontWeight="800" color="white">
                  Vérifier ou corriger mon email
                </Paragraph>
              </YStack>
            </Pressable>
            <Pressable onPress={handleSignOut} accessibilityRole="button">
              <Paragraph
                fontSize={12.5}
                color="$slate500"
                textAlign="center"
                textDecorationLine="underline"
              >
                Se déconnecter et recommencer
              </Paragraph>
            </Pressable>
          </YStack>
        ) : null}

        {!isGuest && !unverified ? (
          <Pressable onPress={() => router.push('/profile')}>
            <YStack
              padding={16}
              borderRadius={16}
              backgroundColor="$slate100"
              gap={4}
            >
              <XStack gap={14} alignItems="center">
                <YStack
                  width={56}
                  height={56}
                  borderRadius={28}
                  backgroundColor={brand.primary}
                  alignItems="center"
                  justifyContent="center"
                >
                  <Paragraph fontSize={22} fontWeight="800" color="white">
                    {meData?.firstname?.charAt(0).toUpperCase() ?? '?'}
                  </Paragraph>
                </YStack>
                <YStack flex={1} gap={2}>
                  <H4 fontSize={17} fontWeight="700" color="$slate900">
                    {meData ? `${meData.firstname} ${meData.lastname ?? ''}`.trim() : 'Chargement…'}
                  </H4>
                  <Paragraph fontSize={13} color="$slate500" numberOfLines={1}>
                    {meData?.email ?? ''}
                  </Paragraph>
                  {balance.data != null && (
                    <XStack alignItems="center" gap={4} marginTop={2}>
                      <CreditCard size={11} color="$slate500" />
                      <Paragraph fontSize={11} fontWeight="700" color="$slate500">
                        {balance.data.toLocaleString('fr-FR')} pts
                      </Paragraph>
                    </XStack>
                  )}
                </YStack>
                <ChevronRight size={18} color="$slate500" />
              </XStack>
            </YStack>
          </Pressable>
        ) : (
          <YStack
            padding={16}
            borderRadius={16}
            backgroundColor={colors.track}
            gap={12}
          >
            <Paragraph fontSize={14} color="$slate700" lineHeight={20}>
              {t('account.guestHint')}
            </Paragraph>
            <XStack gap={10}>
              <Button
                flex={1}
                size="$4"
                backgroundColor="$brand"
                color="white"
                fontWeight="700"
                borderRadius={12}
                icon={<LogIn size={16} color="white" />}
                onPress={() => router.push('/(auth)/login')}
              >
                {t('account.signIn')}
              </Button>
              <Button
                flex={1}
                size="$4"
                backgroundColor={colors.surface}
                color="$color"
                borderColor="$borderColor"
                borderWidth={1}
                fontWeight="700"
                borderRadius={12}
                onPress={() => router.push('/(auth)/register')}
              >
                {t('account.signUp')}
              </Button>
            </XStack>
          </YStack>
        )}

        {isAuthenticated && (
          <Section title="Activité">
            <Row
              icon={<MessageCircle size={18} color="$slate700" />}
              label="Messages"
              onPress={() => router.push('/messages')}
            />
            <Row
              icon={<Bell size={18} color="$slate700" />}
              label="Notifications"
              badge={unread.data && unread.data > 0 ? unread.data : undefined}
              onPress={() => router.push('/notifications')}
            />
            <Row
              icon={<Heart size={18} color="$slate700" />}
              label="Mes favoris"
              onPress={() => router.push('/(tabs)/favorites')}
            />
            <Row
              icon={<Search size={18} color="$slate700" />}
              label="Alertes de recherche"
              onPress={() => router.push('/search-alerts')}
            />
            <Row
              icon={<Calendar size={18} color="$slate700" />}
              label="Mes réservations"
              onPress={() => router.push('/reservations' as never)}
            />
            <Row
              icon={<GitCompareArrows size={18} color="$slate700" />}
              label="Comparateur"
              onPress={() => router.push('/compare')}
            />
          </Section>
        )}

        {isAuthenticated && (
          <Section title="Portefeuille">
            <Row
              icon={<CreditCard size={18} color="$slate700" />}
              label="Crédits & paiements"
              hint={balance.data != null ? `${balance.data.toLocaleString('fr-FR')} crédits disponibles` : undefined}
              onPress={() => router.push('/credits')}
            />
            <Row
              icon={<AlertTriangle size={18} color="$slate700" />}
              label="Litiges"
              onPress={() => router.push('/disputes' as never)}
            />
          </Section>
        )}

        <Section title="Outils">
          <Row
            icon={<MapPin size={18} color="$slate700" />}
            label="Près de moi"
            hint="Annonces autour de votre position"
            onPress={() => router.push('/nearby')}
          />
          <Row
            icon={<Calculator size={18} color="$slate700" />}
            label={t('estimator.title')}
            hint={t('estimator.subtitle')}
            onPress={() => router.push('/estimator')}
          />
          <Row
            icon={<ClipboardList size={18} color="$slate700" />}
            label="Sondages"
            onPress={() => router.push('/surveys' as never)}
          />
          {!isAuthenticated && (
            <Row
              icon={<GitCompareArrows size={18} color="$slate700" />}
              label="Comparateur"
              onPress={() => router.push('/compare')}
            />
          )}
        </Section>

        <Section title="Application">
          <Row
            icon={<User size={18} color="$slate700" />}
            label="Profil et préférences"
            onPress={() =>
              router.push(isAuthenticated ? '/profile' : '/(auth)/login')
            }
          />
          <Row
            icon={<Settings size={18} color="$slate700" />}
            label="Paramètres"
            onPress={() => router.push('/parametres')}
          />
          <Row
            icon={<HelpCircle size={18} color="$slate700" />}
            label="Aide & support"
            onPress={() => router.push('/aide')}
          />
          <Row
            icon={<Mail size={18} color="$slate700" />}
            label="Nous contacter"
            onPress={() => router.push('/contact' as never)}
          />
        </Section>

        {isAuthenticated && (
          <Pressable onPress={handleSignOut}>
            <XStack
              alignItems="center"
              gap={10}
              padding={14}
              borderRadius={12}
              borderWidth={1}
              borderColor="$slate300"
            >
              <LogOut size={18} color={brand.danger} />
              <Paragraph fontSize={15} fontWeight="700" color={brand.danger} flex={1}>
                {t('account.signOut')}
              </Paragraph>
            </XStack>
          </Pressable>
        )}
      </ScrollView>
    </YStack>
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
      <YStack borderRadius={14} overflow="hidden" borderWidth={1} borderColor="$slate300">
        {children}
      </YStack>
    </YStack>
  );
}

function Row({
  icon,
  label,
  hint,
  badge,
  onPress,
}: {
  icon: React.ReactNode;
  label: string;
  hint?: string;
  badge?: number;
  onPress: () => void;
}) {
  return (
    <Pressable onPress={onPress}>
      <XStack
        alignItems="center"
        gap={12}
        paddingHorizontal={14}
        paddingVertical={13}
        backgroundColor="$background"
        borderBottomWidth={1}
        borderBottomColor="$slate100"
      >
        {icon}
        <YStack flex={1} gap={1}>
          <Paragraph fontSize={14.5} fontWeight="600" color="$slate900">
            {label}
          </Paragraph>
          {hint && (
            <Paragraph fontSize={12} color="$slate500" numberOfLines={1}>
              {hint}
            </Paragraph>
          )}
        </YStack>
        {badge != null && badge > 0 && (
          <YStack
            minWidth={22}
            height={22}
            paddingHorizontal={7}
            borderRadius={11}
            backgroundColor={brand.primary}
            alignItems="center"
            justifyContent="center"
          >
            <Paragraph fontSize={11} fontWeight="800" color="white">
              {badge > 99 ? '99+' : badge}
            </Paragraph>
          </YStack>
        )}
        <ChevronRight size={16} color="$slate500" />
      </XStack>
    </Pressable>
  );
}
