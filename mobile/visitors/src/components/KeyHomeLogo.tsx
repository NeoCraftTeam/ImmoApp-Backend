import { KeyRound } from '@tamagui/lucide-icons';
import { Paragraph, XStack, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';

interface Props {
  /** "horizontal" : icône + wordmark côte-à-côte (default).
   *  "stacked"    : icône au-dessus, wordmark dessous (splash / hero).
   *  "icon"       : pastille icône seule (avatar / nav). */
  variant?: 'horizontal' | 'stacked' | 'icon';
  /** Taille de l'icône. Le wordmark scale en conséquence. */
  size?: number;
  /** Couleur principale (icône + mot). Défaut : `brand.primary`. */
  color?: string;
  /** Couleur du fond de la pastille icône. Défaut : `brand.primaryAlpha10`. */
  iconBackground?: string;
  /** Affiche le mot KeyHome ? Forcé `false` quand `variant=icon`. */
  showWordmark?: boolean;
}

/**
 * Logo officiel KeyHome — clé stylisée + wordmark.
 *
 * Reproduit fidèlement la marque du web : pastille teintée brand
 * autour d'un icône `KeyRound` (Lucide) qui matche la clé en SVG
 * utilisée sur keyhome.app. Le wordmark est en `fontWeight: 900`
 * pour conserver le punch visuel à toutes les tailles.
 *
 * Utilisations typiques :
 *   - `<KeyHomeLogo />` en haut des écrans auth (login / register)
 *   - `<KeyHomeLogo variant="stacked" size={48} />` dans le splash
 *   - `<KeyHomeLogo variant="icon" size={20} color="white" iconBackground="rgba(255,255,255,0.18)" />`
 *     dans les headers où il faut juste la pastille
 */
export function KeyHomeLogo({
  variant = 'horizontal',
  size = 22,
  color = brand.primary,
  iconBackground = brand.primaryAlpha10,
  showWordmark = true,
}: Props) {
  const pastilleSize = size * 1.7;
  const wordmarkSize = size * 1.0;

  const icon = (
    <YStack
      width={pastilleSize}
      height={pastilleSize}
      borderRadius={pastilleSize / 2}
      backgroundColor={iconBackground}
      alignItems="center"
      justifyContent="center"
    >
      <KeyRound size={size} color={color} strokeWidth={2.4} />
    </YStack>
  );

  if (variant === 'icon' || !showWordmark) {
    return icon;
  }

  if (variant === 'stacked') {
    return (
      <YStack alignItems="center" gap={12}>
        {icon}
        <Paragraph
          fontSize={wordmarkSize * 1.4}
          fontWeight="900"
          color={color}
          letterSpacing={-0.6}
        >
          KeyHome
        </Paragraph>
      </YStack>
    );
  }

  return (
    <XStack alignItems="center" gap={8}>
      {icon}
      <Paragraph
        fontSize={wordmarkSize}
        fontWeight="900"
        color={color}
        letterSpacing={-0.4}
      >
        KeyHome
      </Paragraph>
    </XStack>
  );
}
