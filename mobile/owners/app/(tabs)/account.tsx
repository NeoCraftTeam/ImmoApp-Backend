import {
  BadgeCheck,
  BarChart3,
  ChevronRight,
  CreditCard,
  FileText,
  HelpCircle,
  LogOut,
  Settings,
  Star,
  User,
  Users,
} from '@tamagui/lucide-icons';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { Alert, Pressable, ScrollView } from 'react-native';
import { H1, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useSession } from '@/auth/SessionProvider';
import { useMe } from '@/hooks/useMe';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';

export default function AccountScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated, signOut } = useSession();
  const me = useMe(isAuthenticated);

  const handleLogout = () => {
    Alert.alert(t('account.title'), t('account.logoutConfirm'), [
      { text: t('common.cancel'), style: 'cancel' },
      {
        text: t('account.logout'),
        style: 'destructive',
        onPress: () => {
          signOut();
          router.replace('/(auth)/login');
        },
      },
    ]);
  };

  const fullName = `${me.data?.firstname ?? ''} ${me.data?.lastname ?? ''}`.trim() || 'Bailleur';

  const items: { icon: React.ReactNode; label: string; route: string; accent?: string }[] = [
    { icon: <User size={20} color={brand.primary} />, label: t('account.profile'), route: '/profile' },
    { icon: <CreditCard size={20} color={brand.accent} />, label: t('account.subscription'), route: '/subscriptions' },
    { icon: <BarChart3 size={20} color={brand.secondary} />, label: t('account.analytics'), route: '/analytics' },
    { icon: <Users size={20} color={brand.primary} />, label: t('account.tenants'), route: '/tenants' },
    { icon: <FileText size={20} color={brand.slate700} />, label: t('account.leases'), route: '/lease-contracts' },
    { icon: <Star size={20} color={brand.accent} />, label: t('account.reviews'), route: '/reviews' },
    { icon: <Settings size={20} color={brand.slate700} />, label: t('account.settings'), route: '/parametres' },
  ];

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScrollView contentContainerStyle={{ paddingTop: insets.top + 12, paddingBottom: 28 }} showsVerticalScrollIndicator={false}>
        <YStack paddingHorizontal={16} marginBottom={18}>
          <H1 fontSize={26} fontWeight="900">
            {t('account.title')}
          </H1>
        </YStack>

        {/* Profile card */}
        <Pressable onPress={() => router.push('/profile' as never)}>
          <XStack
            marginHorizontal={16}
            padding={16}
            borderRadius={18}
            backgroundColor={brand.primaryAlpha10}
            alignItems="center"
            gap={14}
          >
            <YStack width={56} height={56} borderRadius={28} overflow="hidden" backgroundColor="$slate100" alignItems="center" justifyContent="center">
              {me.data?.avatar ? (
                <Image source={{ uri: me.data.avatar }} style={{ width: '100%', height: '100%' }} contentFit="cover" />
              ) : (
                <User size={28} color={brand.primary} />
              )}
            </YStack>
            <YStack flex={1} gap={3}>
              <XStack alignItems="center" gap={6}>
                <Paragraph fontSize={17} fontWeight="800" color="$slate900" numberOfLines={1}>
                  {fullName}
                </Paragraph>
                {me.data?.is_verified ? <BadgeCheck size={16} color={brand.primary} /> : null}
              </XStack>
              <Paragraph fontSize={13} color="$slate500" numberOfLines={1}>
                {me.data?.email ?? ''}
              </Paragraph>
              {me.data?.agency_name ? (
                <Paragraph fontSize={12} color="$slate500" numberOfLines={1}>
                  {me.data.agency_name}
                </Paragraph>
              ) : null}
            </YStack>
            <ChevronRight size={20} color={brand.slate500} />
          </XStack>
        </Pressable>

        {/* Menu */}
        <YStack marginTop={20} marginHorizontal={16} borderRadius={16} borderWidth={1} borderColor="$slate300" overflow="hidden">
          {items.map((item, i) => (
            <Pressable key={item.route} onPress={() => router.push(item.route as never)}>
              <XStack
                alignItems="center"
                gap={14}
                paddingHorizontal={16}
                paddingVertical={15}
                borderTopWidth={i === 0 ? 0 : 0.5}
                borderTopColor="$slate300"
                backgroundColor="$background"
              >
                {item.icon}
                <Paragraph fontSize={15} fontWeight="600" color="$slate900" flex={1}>
                  {item.label}
                </Paragraph>
                <ChevronRight size={18} color={brand.slate500} />
              </XStack>
            </Pressable>
          ))}
        </YStack>

        {/* Help + logout */}
        <YStack marginTop={20} marginHorizontal={16} gap={10}>
          <Pressable onPress={() => router.push('/aide' as never)}>
            <XStack alignItems="center" gap={14} paddingHorizontal={16} paddingVertical={15} borderRadius={16} borderWidth={1} borderColor="$slate300">
              <HelpCircle size={20} color={brand.slate700} />
              <Paragraph fontSize={15} fontWeight="600" color="$slate900" flex={1}>
                {t('account.help')}
              </Paragraph>
              <ChevronRight size={18} color={brand.slate500} />
            </XStack>
          </Pressable>

          <Pressable onPress={handleLogout}>
            <XStack alignItems="center" justifyContent="center" gap={10} paddingVertical={15} borderRadius={16} backgroundColor={`${brand.danger}12`}>
              <LogOut size={18} color={brand.danger} />
              <Paragraph fontSize={15} fontWeight="800" color={brand.danger}>
                {t('account.logout')}
              </Paragraph>
            </XStack>
          </Pressable>
        </YStack>
      </ScrollView>
    </YStack>
  );
}
