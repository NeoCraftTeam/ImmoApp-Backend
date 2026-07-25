import { config } from '@tamagui/config/v3';
import { createTamagui } from 'tamagui';

import { brand } from './src/theme/tokens';

/**
 * Tamagui config bridges KeyHome **owner** brand tokens onto the standard
 * v3 config (which ships the heavy lifting: animations, fonts, media
 * queries). We override colour tokens so `$brand`, `$brandText`,
 * `$accent`, `$success`, `$warning`, `$danger` resolve to the same teal
 * palette the web owner panel uses — keeps the visual identity
 * consistent between Next.js and React Native.
 *
 * Light + dark themes derive from the same brand palette; the default
 * scheme is "automatic" (respects the system setting).
 */
const appConfig = createTamagui({
  ...config,
  tokens: {
    ...config.tokens,
    color: {
      ...config.tokens.color,
      brand: brand.primary,
      brandHover: brand.primaryHover,
      brandLight: brand.primaryLight,
      brandText: brand.primaryText,
      brandAlpha10: brand.primaryAlpha10,
      brandAlpha20: brand.primaryAlpha20,
      secondary: brand.secondary,
      accent: brand.accent,
      accentDark: brand.accentDark,
      success: brand.success,
      warning: brand.warning,
      danger: brand.danger,
      info: brand.info,
      slate900: brand.slate900,
      slate800: brand.slate800,
      slate700: brand.slate700,
      slate600: brand.slate600,
      slate500: brand.slate500,
      slate400: brand.slate400,
      slate300: brand.slate300,
      slate200: brand.slate200,
      slate100: brand.slate100,
    },
  },
  themes: {
    ...config.themes,
    // IMPORTANT : l'échelle slate est redéfinie DANS chaque thème pour
    // être theme-aware (même mécanisme que l'app visiteurs). Toute l'app
    // owner utilise `$slate900/700/500` pour le texte et `$slate100/200`
    // pour surfaces/dividers ; sans ces clés par thème, `$slate900`
    // restait quasi-noir en sombre → texte invisible sur fond sombre.
    // En dark on inverse la luminosité (texte clair, surfaces sombres)
    // tout en gardant la teinte "cool" du brand owner.
    light: {
      ...config.themes.light,
      background: '#FFFFFF',
      color: brand.slate900,
      brand: brand.primary,
      borderColor: brand.slate300,
      slate900: brand.slate900,
      slate800: brand.slate800,
      slate700: brand.slate700,
      slate600: brand.slate600,
      slate500: brand.slate500,
      slate400: brand.slate400,
      slate300: brand.slate300,
      slate200: brand.slate200,
      slate100: brand.slate100,
    },
    dark: {
      ...config.themes.dark,
      background: '#0A0A0F',
      color: '#FFFFFF',
      brand: brand.primary,
      borderColor: brand.slate700,
      // Inversé : slate900 = texte clair … slate100 = surface sombre.
      slate900: '#F8FAFC',
      slate800: '#E2E8F0',
      slate700: '#CBD5E1',
      slate600: '#AAB6C6',
      slate500: '#94A3B8',
      slate400: '#64748B',
      slate300: '#334155',
      slate200: '#2A3646',
      slate100: '#1E293B',
    },
  },
});

/**
 * Le type augmentation `declare module 'tamagui'` vit dans
 * `tamagui-env.d.ts` à côté — esbuild-register échoue à parser
 * un bloc `interface` au milieu d'un fichier runtime quand le
 * babel-plugin Tamagui le `require()` au build-time.
 */
export default appConfig;
