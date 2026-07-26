import { useMemo } from 'react';

import { useAppTheme } from '@/providers/ThemeProvider';
import { brand } from '@/theme/tokens';

export interface ThemeColors {
  scheme: 'light' | 'dark';
  /** Fond principal de l'écran. */
  background: string;
  /** Surface légèrement contrastée (cartes, boutons secondaires, chrome). */
  surface: string;
  /** Fond des rails de progression / skeletons. */
  track: string;
  /** Bordure neutre. */
  border: string;
  /** Overlay quasi-opaque pour les barres flottantes (header ad-detail). */
  chromeOverlay: string;
  /** Remplissage discret (badges type rgba(0,0,0,0.04)). */
  faintFill: string;
  /** Icône / texte secondaire lisible sur `surface` dans les deux thèmes. */
  mutedIcon: string;
}

/**
 * Couleurs sémantiques résolues selon le thème courant. Sert aux endroits
 * qui ne peuvent pas consommer un token Tamagui `$…` directement : styles
 * React Native (`StyleSheet`/`Animated.View`) et valeurs conditionnelles.
 * Pour les composants Tamagui, préférer les tokens (`$background`,
 * `$borderColor`, `$slate…`) — ce hook comble les trous restants.
 */
export function useThemeColors(): ThemeColors {
  const { scheme } = useAppTheme();

  return useMemo<ThemeColors>(() => {
    const dark = scheme === 'dark';
    return {
      scheme,
      background: dark ? '#0A0A0F' : '#FFFFFF',
      surface: dark ? '#17171C' : '#FFFFFF',
      track: dark ? brand.slate700 : brand.slate100,
      border: dark ? brand.slate700 : brand.slate300,
      chromeOverlay: dark ? 'rgba(14,14,19,0.96)' : 'rgba(255,255,255,0.96)',
      faintFill: dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.04)',
      mutedIcon: dark ? brand.slate300 : brand.slate700,
    };
  }, [scheme]);
}
