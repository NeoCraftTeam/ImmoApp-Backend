import * as Haptics from 'expo-haptics';
import { Tabs } from 'expo-router';
import { Heart, Home, Search, User } from '@tamagui/lucide-icons';
import { Platform, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { CompareBar } from '@/components/CompareBar';
import { brand } from '@/theme/tokens';
import { t } from '@/i18n';

const tabPressHaptic = () => {
  void Haptics.selectionAsync();
};

/**
 * Bottom-tab layout — primary navigation surface après onboarding.
 *
 *   Home       — feed
 *   Search     — text + filter sheet
 *   Favorites  — favorited ads (auth-gated, page-level)
 *   Account    — profile + settings + sign-out / sign-in
 *
 * iOS 18 / Dynamic Island / home-indicator devices need an explicit
 * bottom safe-area padding under the tab bar — sans quoi les icônes
 * sont collées au home indicator. On lit `useSafeAreaInsets().bottom`
 * et on l'injecte dans `tabBarStyle.paddingBottom` + dans la hauteur
 * totale, ce qui garantit ~44 pt de touche bien dégagés sur tous les
 * iPhone récents.
 */
const ICON_SIZE = 22;

export default function TabsLayout() {
  const insets = useSafeAreaInsets();

  // iOS home indicator height varies (34 pt typical) ; on Android le
  // gesture-bar inset peut être à 0 sur les vieux téléphones. On force
  // au moins 8 pt de padding sous le label pour le toucher.
  const bottomInset = Math.max(insets.bottom, Platform.OS === 'android' ? 8 : 12);

  return (
    <View style={{ flex: 1 }}>
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
            // Léger drop-shadow vers le haut pour détacher la barre du
            // contenu défilant en-dessous (sans tomber dans l'effet
            // "carte qui flotte" — c'est juste un edge soft).
            shadowColor: 'rgba(0,0,0,0.06)',
            shadowOffset: { width: 0, height: -2 },
            shadowOpacity: 1,
            shadowRadius: 8,
            elevation: 8,
          },
          tabBarItemStyle: {
            paddingTop: 2,
          },
          tabBarHideOnKeyboard: Platform.OS === 'android',
        }}
      >
        <Tabs.Screen
          name="home"
          listeners={{ tabPress: tabPressHaptic }}
          options={{
            title: t('tabs.home'),
            tabBarIcon: ({ color }) => <Home size={ICON_SIZE} color={color} />,
          }}
        />
        <Tabs.Screen
          name="search"
          listeners={{ tabPress: tabPressHaptic }}
          options={{
            title: t('tabs.search'),
            tabBarIcon: ({ color }) => <Search size={ICON_SIZE} color={color} />,
          }}
        />
        <Tabs.Screen
          name="favorites"
          listeners={{ tabPress: tabPressHaptic }}
          options={{
            title: t('tabs.favorites'),
            tabBarIcon: ({ color }) => <Heart size={ICON_SIZE} color={color} />,
          }}
        />
        <Tabs.Screen
          name="account"
          listeners={{ tabPress: tabPressHaptic }}
          options={{
            title: t('tabs.account'),
            tabBarIcon: ({ color }) => <User size={ICON_SIZE} color={color} />,
          }}
        />
      </Tabs>
      {/* Floating compare bar — auto-hides when the compare set is
          empty, so users browsing without comparing never see it. */}
      <CompareBar />
    </View>
  );
}
