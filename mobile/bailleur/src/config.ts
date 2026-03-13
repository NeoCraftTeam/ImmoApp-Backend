import { Platform } from 'react-native';

export const APP_CONFIG = {
  baseUrl: process.env.EXPO_PUBLIC_BASE_URL || 'https://api.keyhome.neocraft.dev/owner',
  apiBaseUrl: (process.env.EXPO_PUBLIC_BASE_URL || 'https://api.keyhome.neocraft.dev/owner').replace(/\/owner$/, ''),
  appMode: 'native' as const,
  scheme: 'keyhome-owner',
  primaryColor: '#10b981',
  accentColor: '#059669',
  splashBg: '#ffffff',
  version: '1.0.0',
} as const;

export const USER_AGENT = `KeyHome/${APP_CONFIG.version} (Owner; ${Platform.OS === 'ios' ? 'iOS' : 'Android'})`;

export const ALLOWED_ORIGINS = [
  'https://keyhomeback.neocraft.dev',
  'https://api.keyhome.neocraft.dev',
  'https://agency.keyhome.neocraft.dev',
  'https://owner.keyhome.neocraft.dev',
] as const;

export const GOOGLE_CONFIG = {
  clientIdAndroid: process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID_ANDROID ?? '',
  clientIdIos: process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID_IOS ?? '',
  clientIdWeb: process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID_WEB ?? '',
} as const;
