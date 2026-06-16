/**
 * KeyHome brand tokens — mirror of `keyhome-frontend-next/src/theme/tokens.ts`
 * so the mobile app reads the same colour values as the web app.
 *
 * Keep this file authoritative for the *mobile* side. If the web tokens
 * change, sync manually rather than importing across the project root
 * (the mobile workspace is isolated for Expo's bundler).
 */
export const brand = {
  /** Primary brand crimson — used for CTAs, marker pins, key score badges. */
  primary: '#F6475F',
  primaryHover: '#E63A50',
  primaryText: '#FFFFFF',
  primaryAlpha10: 'rgba(246, 71, 95, 0.10)',
  primaryAlpha20: 'rgba(246, 71, 95, 0.20)',

  /** Semantic palette (matches the web `semantic` tokens) */
  success: '#16A34A',
  warning: '#F59E0B',
  danger: '#EF4444',
  info: '#2563EB',

  /** Neutral / slate scale — used for text and surfaces */
  slate900: '#0A0A0F',
  slate700: '#1F2937',
  slate500: '#5A5A5A',
  slate300: '#D1D5DB',
  slate100: '#F3F4F6',
} as const;

/** App spacing scale (pixels). Tamagui has its own scale; this is for ad-hoc inline use. */
export const space = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  '2xl': 32,
  '3xl': 48,
} as const;

/** Typography sizes (matches the web's $body / $h6 / $h5 etc. roughly). */
export const fontSize = {
  caption: 11,
  small: 13,
  body: 15,
  bodyLarge: 17,
  h6: 18,
  h5: 22,
  h4: 28,
} as const;

export const radius = {
  sm: 4,
  md: 8,
  lg: 12,
  xl: 16,
  pill: 999,
} as const;
