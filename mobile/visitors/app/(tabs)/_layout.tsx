import { Tabs } from 'expo-router';
import { Heart, Home, Search, User } from '@tamagui/lucide-icons';
import { Platform } from 'react-native';

import { t } from '@/i18n';

/**
 * Bottom-tab layout — the primary navigation surface once the user is
 * past onboarding. Four tabs match the web's customer panel:
 *
 *   Home       — feed
 *   Search     — text + filter sheet
 *   Favorites  — favorited ads (gated on auth; the screen handles the
 *                empty / not-signed-in state itself rather than blocking
 *                navigation, so users can see the tab exists)
 *   Account    — profile + settings + sign-out / sign-in
 *
 * Icons come from `@tamagui/lucide-icons`, which ships the Lucide set
 * already bridged to Tamagui's icon API so they pick up the active
 * tint colour from the theme.
 *
 * `tabBarHideOnKeyboard` (Android) avoids the tab bar floating on top
 * of the keyboard when a search field is focused.
 */
const ICON_SIZE = 22;

export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: '$brand',
        tabBarLabelStyle: { fontSize: 11, fontWeight: '600' },
        tabBarStyle: {
          // The native tab bar uses MUI-spec heights on iOS; bump it
          // slightly on Android so the icon + label don't crowd each
          // other on a tall-letterbox device (Pixel-class screens).
          height: Platform.OS === 'android' ? 64 : undefined,
          paddingTop: 6,
        },
        tabBarHideOnKeyboard: Platform.OS === 'android',
      }}
    >
      <Tabs.Screen
        name="home"
        options={{
          title: t('tabs.home'),
          tabBarIcon: ({ color }) => <Home size={ICON_SIZE} color={color} />,
        }}
      />
      <Tabs.Screen
        name="search"
        options={{
          title: t('tabs.search'),
          tabBarIcon: ({ color }) => <Search size={ICON_SIZE} color={color} />,
        }}
      />
      <Tabs.Screen
        name="favorites"
        options={{
          title: t('tabs.favorites'),
          tabBarIcon: ({ color }) => <Heart size={ICON_SIZE} color={color} />,
        }}
      />
      <Tabs.Screen
        name="account"
        options={{
          title: t('tabs.account'),
          tabBarIcon: ({ color }) => <User size={ICON_SIZE} color={color} />,
        }}
      />
    </Tabs>
  );
}
