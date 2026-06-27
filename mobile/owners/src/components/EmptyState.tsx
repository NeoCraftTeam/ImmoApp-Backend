import type { ReactNode } from 'react';
import { Button, Paragraph, XStack, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';

/**
 * EmptyState pedagogique — pas de "Aucune annonce" sec.
 *
 * Anti-AI-slop (cf. .impeccable.md) :
 *  - Pas de cercle arrondi geant autour de l'icone (DON'T rounded-
 *    icon-container).
 *  - Asymetrie : tout aligne a gauche, pas de centering full-page
 *    (DON'T center everything).
 *  - Le hint doit APPRENDRE l'interface, pas dire "rien ici". Le
 *    composant accepte aussi `tip` (un conseil pro) qui apparait en
 *    petite typo italic — c'est la differenciation owner.
 *  - CTA primaire si action, secondaire si exploration.
 *
 * Inspiration : Linear empty states, Notion blank slates.
 */
export function EmptyState({
  icon,
  title,
  hint,
  tip,
  ctaLabel,
  onPressCta,
  secondaryLabel,
  onPressSecondary,
}: {
  icon?: ReactNode;
  title: string;
  hint?: string;
  /** Conseil pratique en italic — ex. "Une photo nette = +50% de visites". */
  tip?: string;
  ctaLabel?: string;
  onPressCta?: () => void;
  secondaryLabel?: string;
  onPressSecondary?: () => void;
}) {
  return (
    <YStack
      flex={1}
      alignItems="flex-start"
      justifyContent="center"
      paddingHorizontal={24}
      paddingVertical={48}
      gap={14}
    >
      {icon ? (
        <YStack
          width={44}
          height={44}
          alignItems="center"
          justifyContent="center"
          marginBottom={4}
        >
          {icon}
        </YStack>
      ) : null}
      <Paragraph fontSize={20} fontWeight="900" color="$slate900" letterSpacing={-0.3}>
        {title}
      </Paragraph>
      {hint ? (
        <Paragraph fontSize={14} color="$slate500" lineHeight={20} maxWidth="92%">
          {hint}
        </Paragraph>
      ) : null}
      {tip ? (
        <YStack
          marginTop={4}
          paddingLeft={12}
          borderLeftWidth={2}
          borderLeftColor={brand.accent}
        >
          <Paragraph
            fontSize={12.5}
            color={brand.accentDark}
            lineHeight={18}
            fontStyle="italic"
            fontWeight="600"
          >
            {tip}
          </Paragraph>
        </YStack>
      ) : null}
      {ctaLabel || secondaryLabel ? (
        <XStack gap={10} marginTop={8} flexWrap="wrap">
          {ctaLabel && onPressCta ? (
            <Button
              size="$4"
              backgroundColor="$brand"
              color="white"
              fontWeight="800"
              borderRadius={12}
              onPress={onPressCta}
            >
              {ctaLabel}
            </Button>
          ) : null}
          {secondaryLabel && onPressSecondary ? (
            <Button
              size="$4"
              chromeless
              fontWeight="700"
              color={brand.slate700}
              borderRadius={12}
              onPress={onPressSecondary}
            >
              {secondaryLabel}
            </Button>
          ) : null}
        </XStack>
      ) : null}
    </YStack>
  );
}
