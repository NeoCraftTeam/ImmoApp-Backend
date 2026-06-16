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

export type AppConfig = typeof appConfig;

declare module 'tamagui' {
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  interface TamaguiCustomConfig extends AppConfig {}
}

export default appConfig;
