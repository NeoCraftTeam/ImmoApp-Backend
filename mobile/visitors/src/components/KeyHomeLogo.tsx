import { Image } from 'expo-image';
import { Paragraph, XStack, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';

const LOGO_SOURCE = require('../../assets/keyhome-logo.png');

interface Props {
  /** "horizontal" : icône + wordmark côte-à-côte (default).
   *  "stacked"    : icône au-dessus, wordmark dessous (splash / hero).
   *  "icon"       : icône seule (avatar / nav). */
  variant?: 'horizontal' | 'stacked' | 'icon';
  /** Hauteur de référence. L'icône et le wordmark scalent dessus. */
  size?: number;
  /** Couleur du wordmark « KeyHome ». Défaut : `brand.primary`. */
  color?: string;
  /** Affiche le mot KeyHome ? Forcé `false` quand `variant=icon`. */
  showWordmark?: boolean;
}

/**
 * Logo officiel KeyHome — icône « trousseau de clés » de la marque
 * (assets/keyhome-logo.png, repris de public/images/logo.png du web)
 * suivie du wordmark. Le rouge de marque ressort aussi bien sur fond
 * clair que sombre, donc une seule version couvre les deux thèmes.
 *
 * Utilisations typiques :
 *   - `<KeyHomeLogo />` en haut des écrans auth (login / register)
 *   - `<KeyHomeLogo variant="stacked" size={48} />` dans le splash
 *   - `<KeyHomeLogo variant="icon" size={20} />` dans les headers
 */
export function KeyHomeLogo({
  variant = 'horizontal',
  size = 22,
  color = brand.primary,
  showWordmark = true,
}: Props) {
  const iconSize = size * 1.6;
  const wordmarkSize = size * 1.0;

  const icon = (
    <Image
      source={LOGO_SOURCE}
      style={{ width: iconSize, height: iconSize }}
      contentFit="contain"
      accessibilityLabel="KeyHome"
    />
  );

  if (variant === 'icon' || !showWordmark) {
    return icon;
  }

  if (variant === 'stacked') {
    const stackedSize = wordmarkSize * 1.4;
    return (
      <YStack alignItems="center" gap={10}>
        {icon}
        <Paragraph
          fontSize={stackedSize}
          // lineHeight explicite : Tamagui n'adapte pas la hauteur de
          // ligne aux fontSize hors échelle — sans ça le wordmark est
          // tronqué verticalement (vu sur le splash).
          lineHeight={stackedSize * 1.2}
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
        lineHeight={wordmarkSize * 1.25}
        fontWeight="900"
        color={color}
        letterSpacing={-0.4}
      >
        KeyHome
      </Paragraph>
    </XStack>
  );
}
