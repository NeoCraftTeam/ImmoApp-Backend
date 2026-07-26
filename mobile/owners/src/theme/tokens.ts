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

  /**
   * Neutral scale — tintee COOL (teinte vers le cyan/slate) pour
   * cohesion subconsciente avec le teal du brand owner. Aucun
   * `#000` ni `#FFF` pur : tous les neutres ont une teinte du
   * brand (cf. .impeccable.md, "tinted neutrals" → owner = cool).
   */
  slate900: '#0F172A', // slate-900 tailwind, hue cool
  slate800: '#1E293B',
  slate700: '#334155',
  slate600: '#475569',
  slate500: '#64748B',
  slate400: '#94A3B8',
  slate300: '#CBD5E1',
  slate200: '#E2E8F0',
  slate100: '#F1F5F9',
  slate50: '#F8FAFC',

  /** Background app (off-white tinte cool, jamais `#FFF` pur). */
  surface: '#FAFBFC',
  surfaceMuted: '#F1F5F9',
} as const;

/** Brand gradients (consumed via expo-linear-gradient-free fallbacks / inline). */
export const gradient = {
  agent: ['#0D9488', '#0EA5E9'] as const,
  agentGold: ['#0D9488', '#F59E0B'] as const,
} as const;

/**
 * Spacing rhythm varie — NON-lineaire (cf. .impeccable.md,
 * « JAMAIS le meme padding partout »). Echelle alterne tightness
 * et generositee pour creer du rythme visuel : 4-6-10-14-20-32-48-72.
 */
export const space = {
  xs: 4,
  sm: 6,
  md: 10,
  lg: 14,
  xl: 20,
  '2xl': 32,
  '3xl': 48,
  '4xl': 72,
} as const;

/**
 * Type scale modulaire Major Third (ratio 1.250) — calculee pour
 * mobile portrait. Caption a body : 11/13/15. Body a XL : 15/22/28/36.
 * Tabular numerals pour les montants financiers (cf. owner brand
 * « typo qui inspire confiance pour les chiffres »).
 */
export const fontSize = {
  caption: 11,
  small: 13,
  body: 15,
  bodyLarge: 17,
  h6: 18,
  h5: 22,
  h4: 28,
  h3: 36,
  hero: 48,
} as const;

/** Style font-variant pour les chiffres FCFA (alignement decimal). */
export const tabularNumStyle = {
  fontVariant: ['tabular-nums' as const],
  fontFeatureSettings: '"tnum"',
};

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
