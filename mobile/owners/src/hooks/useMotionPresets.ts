import { useReducedMotion } from '@/hooks/useReducedMotion';

/**
 * Presets motion dérivés du réglage « Réduire les animations » (HIG).
 * Centralise les choix Tamagui / ScrollView pour éviter les slides,
 * parallax et springs décoratifs quand l'utilisateur les a désactivés.
 */
export function useMotionPresets() {
  const reducedMotion = useReducedMotion();

  return {
    reducedMotion,
    tamaguiQuick: reducedMotion ? undefined : ('quick' as const),
    tamaguiMedium: reducedMotion ? undefined : ('medium' as const),
    tamaguiLazy: reducedMotion ? undefined : ('lazy' as const),
    scrollAnimated: !reducedMotion,
    fadeMs: reducedMotion ? 160 : 320,
    splashFadeMs: reducedMotion ? 180 : 380,
  } as const;
}
