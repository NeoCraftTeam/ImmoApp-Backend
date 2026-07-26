import { useEffect, useState } from 'react';
import { AccessibilityInfo } from 'react-native';

/**
 * Reflète le réglage système « Réduire les animations » (iOS) /
 * « Supprimer les animations » (Android).
 *
 * Principe Apple (Designing Fluid Interfaces + HIG) : reduced motion ne
 * veut pas dire zéro feedback — on remplace slides / springs / parallax
 * par des cross-fades courts et on stoppe les boucles infinies, mais on
 * garde les changements d'opacité et de couleur qui aident la
 * compréhension.
 *
 * Valeur initiale `false` (synchronement inconnue) puis corrigée dès la
 * réponse d'`AccessibilityInfo` — au pire une entrée animée sur le tout
 * premier frame, jamais l'inverse.
 */
export function useReducedMotion(): boolean {
  const [reduced, setReduced] = useState(false);

  useEffect(() => {
    let mounted = true;

    void AccessibilityInfo.isReduceMotionEnabled().then((enabled) => {
      if (mounted) setReduced(enabled);
    });

    const sub = AccessibilityInfo.addEventListener(
      'reduceMotionChanged',
      setReduced,
    );

    return () => {
      mounted = false;
      sub.remove();
    };
  }, []);

  return reduced;
}
