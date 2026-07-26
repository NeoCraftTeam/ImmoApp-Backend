import { config } from '@tamagui/config/v3';
import { createTamagui } from 'tamagui';

import { brand } from './src/theme/tokens';

/**
 * Tamagui config bridges KeyHome brand tokens onto the standard v3
 * config (it ships the heavy lifting: animations, fonts, media queries).
 * We override colour tokens so `$brand`, `$brandText`, `$success`,
 * `$warning`, `$danger` resolve to the same values the web app uses —
 * keeps the visual identity consistent between Next.js and React Native.
 *
 * Light + dark themes are derived from the same brand palette; the
 * default scheme is "automatic" (respects the system setting, opt-in to
 * dark via Settings → Appearance on the device).
 */
const appConfig = createTamagui({
  ...config,
  tokens: {
    ...config.tokens,
    color: {
      ...config.tokens.color,
      brand: brand.primary,
      brandHover: brand.primaryHover,
      brandText: brand.primaryText,
      brandAlpha10: brand.primaryAlpha10,
      brandAlpha20: brand.primaryAlpha20,
      success: brand.success,
      warning: brand.warning,
      danger: brand.danger,
      slate900: brand.slate900,
      slate700: brand.slate700,
      slate500: brand.slate500,
      slate300: brand.slate300,
      slate100: brand.slate100,
    },
  },
  themes: {
    ...config.themes,
    // IMPORTANT : l'échelle slate est redéfinie DANS chaque thème pour
    // être theme-aware. Toute l'app utilise `$slate900/700/500` pour le
    // texte et `$slate100` pour les surfaces ; sans ces clés par thème,
    // `$slate900` restait quasi-noir en sombre → texte invisible. En
    // dark on inverse la luminosité (texte clair, surfaces sombres).
    light: {
      ...config.themes.light,
      background: '#FFFFFF',
      backgroundHover: '#F3F4F6',
      color: brand.slate900,
      brand: brand.primary,
      brandText: brand.primaryText,
      borderColor: brand.slate300,
      slate900: brand.slate900,
      slate700: brand.slate700,
      slate500: brand.slate500,
      slate300: brand.slate300,
      slate100: brand.slate100,
      brandAlpha10: brand.primaryAlpha10,
    },
    dark: {
      ...config.themes.dark,
      background: '#0A0A0F',
      backgroundHover: '#1E293B',
      color: '#F8FAFC',
      brand: brand.primary,
      brandText: brand.primaryText,
      borderColor: '#334155',
      // Inversé : slate900 = texte clair, slate100 = surface sombre.
      slate900: '#F8FAFC',
      slate700: '#CBD5E1',
      slate500: '#94A3B8',
      slate300: '#334155',
      slate100: '#1E293B',
      brandAlpha10: 'rgba(246, 71, 95, 0.16)',
    },
  },
});

/**
 * Le type augmentation `declare module 'tamagui'` vit dans
 * `tamagui-env.d.ts` à côté — voir le commentaire en tête du
 * fichier .d.ts. Le mélanger ici cassait l'évaluation CJS du
 * config faite par `esbuild-register` à travers `@tamagui/babel-plugin`
 * (« Unexpected token '{' » sur le bloc `interface`).
 */
export default appConfig;
