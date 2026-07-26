import { Tabs } from 'expo-router';
import { CalendarCheck, LayoutDashboard, Menu, Building2 } from '@tamagui/lucide-icons';
import { Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { brand } from '@/theme/tokens';
import { t } from '@/i18n';

/**
 * Bottom-tab layout — the owner's primary navigation.
 *
 *   Dashboard  — KPIs, recent ads, pending viewings
 *   Annonces   — manage listings (create / edit / boost / placarde)
 *   Visites    — viewing-request inbox
 *   Plus       — profile, subscription, tenants, leases, settings…
 */
const ICON_SIZE = 22;

export default function TabsLayout() {
  const insets = useSafeAreaInsets();
  const bottomInset = Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 12);

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: brand.primary,
        tabBarInactiveTintColor: brand.slate500,
        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: '600',
          marginBottom: Platform.OS === 'ios' ? 0 : 4,
        },
        tabBarStyle: {
          backgroundColor: 'white',
          borderTopColor: brand.slate300,
          borderTopWidth: 0.5,
          height: 56 + bottomInset,
          paddingTop: 8,
          paddingBottom: bottomInset,
          shadowColor: 'rgba(0,0,0,0.06)',
          shadowOffset: { width: 0, height: -2 },
          shadowOpacity: 1,
          shadowRadius: 8,
          elevation: 8,
        },
        tabBarItemStyle: { paddingTop: 2 },
        tabBarHideOnKeyboard: Platform.OS === 'android',
      }}
    >
      <Tabs.Screen
        name="dashboard"
        options={{
          title: t('tabs.dashboard'),
          tabBarIcon: ({ color }) => <LayoutDashboard size={ICON_SIZE} color={color} />,
        }}
      />
      <Tabs.Screen
        name="ads"
        options={{
          title: t('tabs.ads'),
          tabBarIcon: ({ color }) => <Building2 size={ICON_SIZE} color={color} />,
        }}
      />
      <Tabs.Screen
        name="viewings"
        options={{
          title: t('tabs.viewings'),
          tabBarIcon: ({ color }) => <CalendarCheck size={ICON_SIZE} color={color} />,
        }}
      />
      <Tabs.Screen
        name="account"
        options={{
          title: t('tabs.account'),
          tabBarIcon: ({ color }) => <Menu size={ICON_SIZE} color={color} />,
        }}
      />
    </Tabs>
  );
}
