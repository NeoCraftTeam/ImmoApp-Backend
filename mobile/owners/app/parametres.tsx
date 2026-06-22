import { Bell, LogOut, Mail, Smartphone, Trash2 } from '@tamagui/lucide-icons';
import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, Pressable, ScrollView } from 'react-native';
import { Paragraph, Spinner, XStack, YStack } from 'tamagui';

import { apiClient, extractApiErrorMessage } from '@/api/client';
import { ENDPOINTS } from '@/api/endpoints';
import { useSession } from '@/auth/SessionProvider';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useMe } from '@/hooks/useMe';
import { useUpdateProfile } from '@/hooks/useProfile';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';

/**
 * Owner settings — notification channels (persisted per toggle) and the
 * danger zone (account deletion + sign-out). Notification state is
 * initialised from `/auth/me` and patched through `/users/{id}`.
 */
export default function SettingsScreen() {
  const router = useRouter();
  const { isAuthenticated, signOut } = useSession();
  const me = useMe(isAuthenticated);
  const userId = me.data?.id;
  const updateProfile = useUpdateProfile(userId);

  const [emailOn, setEmailOn] = useState(false);
  const [pushOn, setPushOn] = useState(false);
  const [deleting, setDeleting] = useState(false);

  useEffect(() => {
    if (!me.data) return;
    setEmailOn(me.data.notification_email ?? false);
    setPushOn(me.data.notification_push ?? false);
  }, [me.data]);

  const persist = async (
    field: 'notification_email' | 'notification_push',
    value: boolean,
    revert: (v: boolean) => void,
  ) => {
    try {
      await updateProfile.mutateAsync({ [field]: value });
    } catch (err) {
      revert(!value);
      Alert.alert(t('common.error'), extractApiErrorMessage(err));
    }
  };

  const toggleEmail = (value: boolean) => {
    setEmailOn(value);
    void persist('notification_email', value, setEmailOn);
  };
  const togglePush = (value: boolean) => {
    setPushOn(value);
    void persist('notification_push', value, setPushOn);
  };

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

  const handleDeleteAccount = () => {
    Alert.alert(
      t('settings.deleteAccount'),
      'Cette action est définitive : votre compte et vos données seront supprimés. Continuer ?',
      [
        { text: t('common.cancel'), style: 'cancel' },
        {
          text: t('common.delete'),
          style: 'destructive',
          onPress: async () => {
            setDeleting(true);
            try {
              await apiClient.delete(ENDPOINTS.my.deleteAccount);
              signOut();
              router.replace('/(auth)/login');
            } catch (err) {
              Alert.alert(t('common.error'), extractApiErrorMessage(err));
            } finally {
              setDeleting(false);
            }
          },
        },
      ],
    );
  };

  return (
    <YStack flex={1} backgroundColor="$background">
      <ScreenHeader title={t('settings.title')} />

      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }} showsVerticalScrollIndicator={false}>
        {/* Notifications */}
        <SectionTitle icon={<Bell size={18} color={brand.primary} />} label={t('settings.notifications')} />

        <YStack borderWidth={1} borderColor="$slate300" borderRadius={16} overflow="hidden" marginTop={12}>
          <ToggleRow
            icon={<Mail size={18} color={brand.slate700} />}
            label={t('settings.channelEmail')}
            value={emailOn}
            disabled={updateProfile.isPending}
            onChange={toggleEmail}
            first
          />
          <ToggleRow
            icon={<Smartphone size={18} color={brand.slate700} />}
            label={t('settings.channelPush')}
            value={pushOn}
            disabled={updateProfile.isPending}
            onChange={togglePush}
          />
        </YStack>

        {/* Danger zone */}
        <YStack marginTop={32} gap={6}>
          <Paragraph fontSize={13} fontWeight="900" color={brand.danger}>
            Zone de danger
          </Paragraph>
        </YStack>

        <YStack marginTop={12} gap={10}>
          <Pressable onPress={handleDeleteAccount} disabled={deleting}>
            <XStack
              alignItems="center"
              gap={12}
              paddingHorizontal={16}
              paddingVertical={15}
              borderRadius={14}
              borderWidth={1}
              borderColor="$danger"
            >
              {deleting ? <Spinner color={brand.danger} /> : <Trash2 size={18} color={brand.danger} />}
              <Paragraph fontSize={15} fontWeight="800" color="$danger" flex={1}>
                {t('settings.deleteAccount')}
              </Paragraph>
            </XStack>
          </Pressable>

          <Pressable onPress={handleLogout}>
            <XStack
              alignItems="center"
              justifyContent="center"
              gap={10}
              paddingVertical={15}
              borderRadius={14}
              backgroundColor={`${brand.danger}12`}
            >
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

function SectionTitle({ icon, label }: { icon: React.ReactNode; label: string }) {
  return (
    <XStack alignItems="center" gap={8}>
      {icon}
      <Paragraph fontSize={16} fontWeight="900" color="$slate900">
        {label}
      </Paragraph>
    </XStack>
  );
}

function ToggleRow({
  icon,
  label,
  value,
  disabled,
  onChange,
  first,
}: {
  icon: React.ReactNode;
  label: string;
  value: boolean;
  disabled?: boolean;
  onChange: (next: boolean) => void;
  first?: boolean;
}) {
  return (
    <XStack
      alignItems="center"
      gap={12}
      paddingHorizontal={16}
      paddingVertical={15}
      backgroundColor="$background"
      borderTopWidth={first ? 0 : 0.5}
      borderTopColor="$slate300"
    >
      {icon}
      <Paragraph fontSize={15} fontWeight="600" color="$slate900" flex={1}>
        {label}
      </Paragraph>
      <Pressable
        onPress={() => onChange(!value)}
        disabled={disabled}
        accessibilityRole="switch"
        accessibilityState={{ checked: value }}
      >
        <YStack
          width={48}
          height={28}
          borderRadius={14}
          padding={3}
          justifyContent="center"
          backgroundColor={value ? brand.primary : brand.slate300}
          opacity={disabled ? 0.6 : 1}
        >
          <YStack
            width={22}
            height={22}
            borderRadius={11}
            backgroundColor="white"
            alignSelf={value ? 'flex-end' : 'flex-start'}
          />
        </YStack>
      </Pressable>
    </XStack>
  );
}
