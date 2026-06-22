import {
  ArrowLeft,
  Bell,
  BellRing,
  CheckCheck,
  CreditCard,
  Heart,
  Home,
  MessageCircle,
  Search,
  Trash2,
} from '@tamagui/lucide-icons';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { Stack, useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, FlatList, Pressable } from 'react-native';
import { H2, Paragraph, XStack, YStack } from 'tamagui';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { extractApiErrorMessage } from '@/api/client';
import {
  useDeleteNotification,
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotifications,
} from '@/hooks/useNotifications';
import { useSession } from '@/auth/SessionProvider';
import { brand } from '@/theme/tokens';
import type { AppNotification } from '@/types/notification';

/**
 * Notification centre — two tabs (Toutes / Non lues), per-row swipe-free
 * delete via an inline trash button, and a "tout marquer comme lu" bulk
 * action in the header. Tapping a row that carries `data.href` deep-links
 * into the relevant screen.
 */
export default function Notifications() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { isAuthenticated } = useSession();
  const [unreadOnly, setUnreadOnly] = useState(false);

  const { data, isLoading, isError, error, refetch, isRefetching } =
    useNotifications(unreadOnly);
  const markRead = useMarkNotificationRead();
  const markAll = useMarkAllNotificationsRead();
  const remove = useDeleteNotification();

  if (!isAuthenticated) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5" gap={10}>
        <BellRing size={36} color={brand.slate500} />
        <Paragraph fontSize={15} color="$slate900" textAlign="center" fontWeight="700">
          Connectez-vous pour voir vos notifications
        </Paragraph>
        <Pressable onPress={() => router.push('/(auth)/login')}>
          <XStack backgroundColor="$brand" paddingHorizontal={18} paddingVertical={10} borderRadius={10}>
            <Paragraph color="white" fontWeight="700">Se connecter</Paragraph>
          </XStack>
        </Pressable>
      </YStack>
    );
  }

  const handlePress = (notif: AppNotification) => {
    if (!notif.read_at) {
      markRead.mutate(notif.id);
    }
    if (notif.href) {
      router.push(notif.href as never);
    }
  };

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <YStack flex={1} backgroundColor="$background">
        <YStack
          paddingTop={insets.top + 8}
          paddingHorizontal={14}
          paddingBottom={10}
          gap={10}
          borderBottomWidth={1}
          borderBottomColor="$slate300"
        >
          <XStack alignItems="center" gap={10}>
            <Pressable onPress={() => router.back()} hitSlop={8} accessibilityRole="button" accessibilityLabel="Retour">
              <YStack width={36} height={36} borderRadius={18} backgroundColor="$slate100" alignItems="center" justifyContent="center">
                <ArrowLeft size={18} color={brand.slate700} />
              </YStack>
            </Pressable>
            <H2 fontSize={20} fontWeight="700" color="$slate900" flex={1}>
              Notifications
            </H2>
            <Pressable onPress={() => markAll.mutate()} hitSlop={6}>
              <XStack
                alignItems="center"
                gap={6}
                paddingHorizontal={10}
                paddingVertical={6}
                borderRadius={999}
                backgroundColor="$slate100"
              >
                <CheckCheck size={14} color={brand.slate700} />
                <Paragraph fontSize={12} fontWeight="700" color="$slate700">
                  Tout lu
                </Paragraph>
              </XStack>
            </Pressable>
          </XStack>
          <XStack gap={8}>
            <Tab
              active={!unreadOnly}
              label="Toutes"
              onPress={() => setUnreadOnly(false)}
            />
            <Tab
              active={unreadOnly}
              label="Non lues"
              onPress={() => setUnreadOnly(true)}
            />
          </XStack>
        </YStack>

        {isLoading ? (
          <YStack flex={1} alignItems="center" justifyContent="center">
            <ActivityIndicator />
          </YStack>
        ) : isError ? (
          <YStack padding="$5" alignItems="center">
            <Paragraph color="$slate700">{extractApiErrorMessage(error)}</Paragraph>
          </YStack>
        ) : (
          <FlatList
            data={data ?? []}
            keyExtractor={(item) => item.id}
            onRefresh={() => refetch()}
            refreshing={isRefetching}
            contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
            ItemSeparatorComponent={() => <YStack height={1} backgroundColor="$slate100" marginLeft={60} />}
            ListEmptyComponent={
              <YStack padding="$6" alignItems="center" gap={6}>
                <Bell size={36} color={brand.slate500} />
                <Paragraph fontSize={14} color="$slate900" fontWeight="700">
                  Pas de notifications
                </Paragraph>
                <Paragraph fontSize={12} color="$slate500" textAlign="center">
                  Vous serez notifié des nouvelles annonces, messages et paiements.
                </Paragraph>
              </YStack>
            }
            renderItem={({ item }) => (
              <NotificationRow
                notif={item}
                onPress={() => handlePress(item)}
                onDelete={() => remove.mutate(item.id)}
              />
            )}
          />
        )}
      </YStack>
    </>
  );
}

function Tab({
  active,
  label,
  onPress,
}: {
  active: boolean;
  label: string;
  onPress: () => void;
}) {
  return (
    <Pressable onPress={onPress} hitSlop={4}>
      <XStack
        paddingHorizontal={14}
        paddingVertical={7}
        borderRadius={999}
        backgroundColor={active ? brand.slate900 : '$slate100'}
      >
        <Paragraph fontSize={13} fontWeight="700" color={active ? 'white' : '$slate700'}>
          {label}
        </Paragraph>
      </XStack>
    </Pressable>
  );
}

function NotificationRow({
  notif,
  onPress,
  onDelete,
}: {
  notif: AppNotification;
  onPress: () => void;
  onDelete: () => void;
}) {
  const unread = !notif.read_at;
  const { icon, tint } = iconFor(notif.type);
  const relative = (() => {
    try {
      return formatDistanceToNow(new Date(notif.created_at), {
        addSuffix: true,
        locale: fr,
      });
    } catch {
      return '';
    }
  })();

  return (
    <Pressable onPress={onPress}>
      <XStack
        paddingVertical={12}
        paddingHorizontal={14}
        gap={12}
        alignItems="flex-start"
        backgroundColor={unread ? `${brand.primaryAlpha10}` : 'transparent'}
      >
        <YStack
          width={38}
          height={38}
          borderRadius={19}
          backgroundColor={`${tint}20`}
          alignItems="center"
          justifyContent="center"
        >
          {icon}
        </YStack>
        <YStack flex={1} gap={2}>
          <XStack alignItems="center" justifyContent="space-between" gap={6}>
            <Paragraph
              fontSize={14}
              fontWeight={unread ? '800' : '600'}
              color="$slate900"
              flex={1}
            >
              {notif.title}
            </Paragraph>
            <Paragraph fontSize={11} color="$slate500">
              {relative}
            </Paragraph>
          </XStack>
          {notif.body && (
            <Paragraph fontSize={13} color="$slate700" lineHeight={18} numberOfLines={3}>
              {notif.body}
            </Paragraph>
          )}
        </YStack>
        <Pressable onPress={onDelete} hitSlop={6}>
          <YStack padding={4}>
            <Trash2 size={15} color={brand.slate500} />
          </YStack>
        </Pressable>
      </XStack>
    </Pressable>
  );
}

function iconFor(type: string): { icon: React.ReactNode; tint: string } {
  const t = type.toLowerCase();
  if (t.includes('payment') || t.includes('credit')) {
    return { icon: <CreditCard size={18} color={brand.success} />, tint: brand.success };
  }
  if (t.includes('ad')) {
    return { icon: <Home size={18} color={brand.primary} />, tint: brand.primary };
  }
  if (t.includes('review')) {
    return { icon: <Heart size={18} color={brand.warning} />, tint: brand.warning };
  }
  if (t.includes('search') || t.includes('alert')) {
    return { icon: <Search size={18} color={brand.info} />, tint: brand.info };
  }
  if (t.includes('message') || t.includes('chat')) {
    return { icon: <MessageCircle size={18} color={brand.info} />, tint: brand.info };
  }
  return { icon: <Bell size={18} color={brand.slate700} />, tint: brand.slate700 };
}
