/**
 * KeyHome **owner / bailleur** brand tokens — mirror of the web
 * `keyhome-frontend-next` `brandAgent` palette. The owner experience is
 * deliberately TEAL (vs. the visitor app's crimson) so the two audiences
 * read as distinct products at a glance: visitors browse, owners manage.
 *
 * Keep this file authoritative for the *mobile owner* side. If the web
 * `brandAgent` tokens change, sync manually — the mobile workspace is
 * isolated for Expo's bundler and can't import across the project root.
 */
export const brand = {
  /** Primary teal — CTAs, active tab, status accents, dashboard charts. */
  primary: '#0D9488',
  primaryHover: '#0F766E',
  primaryLight: '#14B8A6',
  primaryText: '#FFFFFF',
  primaryAlpha10: 'rgba(13, 148, 136, 0.10)',
  primaryAlpha20: 'rgba(13, 148, 136, 0.20)',

  /** Sky-blue secondary — used in gradients + secondary chips. */
  secondary: '#0EA5E9',
  secondaryDark: '#0284C7',

  /** Gold accent — premium / boost / verification surfaces. */
  accent: '#F59E0B',
  accentDark: '#D97706',
  accentLight: '#FBBF24',
  accentAlpha10: 'rgba(245, 158, 11, 0.10)',

  /** Semantic palette (shared with the web `semantic` tokens). */
  success: '#16A34A',
  warning: '#F59E0B',
  danger: '#EF4444',
  info: '#2563EB',

  /** Neutral / slate scale — used for text and surfaces. */
  slate900: '#0A0A0F',
  slate700: '#1F2937',
  slate500: '#5A5A5A',
  slate300: '#D1D5DB',
  slate100: '#F3F4F6',
} as const;

/** Brand gradients (consumed via expo-linear-gradient-free fallbacks / inline). */
export const gradient = {
  agent: ['#0D9488', '#0EA5E9'] as const,
  agentGold: ['#0D9488', '#F59E0B'] as const,
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

/** Typography sizes (roughly matches the web's $body / $h6 / $h5 etc.). */
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

/**
 * Ad-status → colour + French label map. Mirrors the backend `AdStatus`
 * enum (`draft|pending|available|reserved|rent|sold|declined`). Used by
 * the `StatusBadge` component and the ads-list filter chips.
 */
export const AD_STATUS_META: Record<
  string,
  { label: string; color: string; bg: string }
> = {
  draft: { label: 'Brouillon', color: brand.slate700, bg: brand.slate100 },
  pending: { label: 'En attente', color: brand.accentDark, bg: brand.accentAlpha10 },
  available: { label: 'Disponible', color: brand.success, bg: 'rgba(22,163,74,0.10)' },
  reserved: { label: 'Réservé', color: brand.secondaryDark, bg: 'rgba(14,165,233,0.10)' },
  rent: { label: 'Loué', color: brand.primaryHover, bg: brand.primaryAlpha10 },
  sold: { label: 'Vendu', color: brand.primaryHover, bg: brand.primaryAlpha10 },
  declined: { label: 'Refusé', color: brand.danger, bg: 'rgba(239,68,68,0.10)' },
};
