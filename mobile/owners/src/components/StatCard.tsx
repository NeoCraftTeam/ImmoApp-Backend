import type { ReactNode } from 'react';
import { Paragraph, XStack, YStack } from 'tamagui';

import { brand, tabularNumStyle } from '@/theme/tokens';

/**
 * KPI compact pour les écrans secondaires (PAS pour le dashboard
 * principal — celui-ci utilise InlineMetric inline, voir
 * `app/(tabs)/dashboard.tsx`). Pattern intentionnellement minimal :
 *
 *  - Pas de container arrondi autour de l'icone (= AI signature
 *    « rounded-icon-above-heading »). L'icone est nue, alignee
 *    horizontalement avec le label en petite typo.
 *  - Pas de bordure pleine — juste une fine ligne de gauche tintee
 *    avec l'accent du KPI (= signal couleur sans bruit visuel).
 *  - Chiffre dominant en tabular-nums (alignement decimal sur les
 *    montants FCFA, parite Stripe-like).
 *  - Hint optionnel en petite typo.
 *
 * Cette refonte fait suite a l'audit `frontend-design` qui flaggait
 * la version precedente (cards bordurees + rounded-icon container)
 * comme "AI-slop typique" (.impeccable.md / DON'T identical card
 * grids + rounded-icon).
 */
export function StatCard({
  label,
  value,
  icon,
  accent = brand.primary,
  hint,
}: {
  label: string;
  value: string | number;
  icon?: ReactNode;
  accent?: string;
  hint?: string;
}) {
  return (
    <YStack
      flex={1}
      minWidth={140}
      paddingLeft={14}
      paddingVertical={4}
      gap={6}
      borderLeftWidth={2}
      borderLeftColor={accent}
    >
      <XStack alignItems="center" gap={6}>
        {icon}
        <Paragraph
          fontSize={11}
          fontWeight="700"
          color="$slate500"
          letterSpacing={0.6}
          textTransform="uppercase"
          numberOfLines={1}
        >
          {label}
        </Paragraph>
      </XStack>
      <Paragraph
        fontSize={26}
        fontWeight="900"
        color="$slate900"
        letterSpacing={-0.6}
        style={tabularNumStyle}
      >
        {value}
      </Paragraph>
      {hint ? (
        <Paragraph fontSize={11.5} color="$slate500" fontWeight="600">
          {hint}
        </Paragraph>
      ) : null}
    </YStack>
  );
}
