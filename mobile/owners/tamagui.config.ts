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
      slate700: brand.slate700,
      slate500: brand.slate500,
      slate300: brand.slate300,
      slate100: brand.slate100,
    },
  },
  themes: {
    ...config.themes,
    light: {
      ...config.themes.light,
      background: '#FFFFFF',
      color: brand.slate900,
      brand: brand.primary,
      borderColor: brand.slate300,
    },
    dark: {
      ...config.themes.dark,
      background: '#0A0A0F',
      color: '#FFFFFF',
      brand: brand.primary,
      borderColor: brand.slate700,
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
