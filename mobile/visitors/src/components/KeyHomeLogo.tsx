import { useMemo } from 'react';
import { SvgXml } from 'react-native-svg';
import { Paragraph, XStack, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';

interface Props {
  /** "horizontal" : icône + wordmark côte-à-côte (default).
   *  "stacked"    : icône au-dessus, wordmark dessous (splash / hero).
   *  "icon"       : pastille icône seule (avatar / nav). */
  variant?: 'horizontal' | 'stacked' | 'icon';
  /** Taille de l'icône. Le wordmark scale en conséquence. */
  size?: number;
  /** Couleur du wordmark « KeyHome ». Défaut : `brand.primary`. */
  color?: string;
  /** Couleur de fond de la pastille. Défaut : `brand.primary` (logo officiel). */
  iconBackground?: string;
  /** Couleur de la clé dans la pastille. Défaut : blanc (logo officiel). */
  markColor?: string;
  /** Affiche le mot KeyHome ? Forcé `false` quand `variant=icon`. */
  showWordmark?: boolean;
}

/**
 * Tracé vectoriel officiel de la clé KeyHome, repris à l'identique du
 * logo de marque (`public/brand/bimi-logo.svg`). Le `<g>` conserve la
 * transform d'origine (`translate(0,512) scale(0.1,-0.1)`) ; seule la
 * couleur de remplissage est paramétrée pour permettre les variantes
 * sur fond clair / foncé.
 */
function keyMarkXml(fill: string): string {
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><g transform="translate(0,512) scale(0.1,-0.1)" fill="${fill}" stroke="none"><path d="M2606 3409 c-61 -9 -157 -48 -189 -77 l-19 -17 -122 -1 -121 -1 -80 -28 c-44 -16 -102 -43 -130 -60 -153 -98 -248 -284 -230 -451 l6 -61 -70 -62 -71 -63 0 -43 0 -43 -41 -36 -42 -35 6 -44 c8 -54 -12 -77 -65 -77 l-36 0 -6 -33 c-8 -43 -72 -86 -122 -83 l-34 3 0 -19 c0 -10 -12 -30 -26 -43 l-27 -25 -63 0 -64 0 0 -19 c0 -13 -17 -29 -50 -47 l-50 -27 0 -33 0 -33 -37 -26 c-21 -14 -47 -25 -59 -25 l-21 0 -18 -106 c-12 -73 -15 -114 -9 -133 l9 -26 58 -11 c32 -7 77 -15 100 -18 l42 -6 80 60 c44 34 103 78 130 100 51 39 300 230 371 285 22 16 88 67 148 114 l108 84 44 -21 44 -22 32 28 c18 16 48 39 67 52 l33 23 50 -12 c27 -6 91 -11 141 -11 86 0 151 15 238 54 13 6 25 -8 54 -61 69 -123 177 -228 300 -290 60 -31 188 -64 270 -70 117 -9 276 27 379 86 413 239 478 802 130 1120 -80 73 -175 126 -282 157 l-94 26 -123 -4 c-69 -2 -128 -7 -132 -11 -4 -5 -3 -15 3 -24 l10 -16 139 -1 140 -2 69 -24 c298 -104 477 -389 437 -695 -22 -162 -82 -277 -204 -392 -68 -63 -194 -132 -278 -152 -316 -74 -636 95 -759 402 -39 98 -53 196 -40 286 12 87 62 225 103 288 53 81 160 178 243 220 l75 38 15 -19 c21 -29 18 -35 -23 -53 -53 -22 -150 -93 -200 -146 -52 -56 -106 -153 -129 -232 -25 -87 -22 -251 5 -332 82 -244 299 -403 549 -403 183 0 334 68 452 205 102 118 154 299 130 450 -40 249 -217 441 -453 489 -84 18 -163 20 -219 7 l-38 -9 -32 60 c-35 65 -68 97 -137 133 -84 42 -173 57 -265 44z m186 -33 c60 -19 83 -31 136 -74 35 -29 83 -92 69 -92 -2 0 -28 24 -59 54 -69 67 -98 83 -201 106 l-82 18 45 1 c25 0 66 -6 92 -13z m-44 -27 c51 -10 153 -65 159 -85 2 -6 -10 -17 -27 -24 -16 -7 -62 -34 -101 -61 -39 -27 -73 -49 -76 -49 -2 0 -30 22 -61 49 -69 60 -188 117 -208 100 -11 -9 2 -19 63 -50 80 -40 173 -112 173 -132 -1 -7 -19 -32 -41 -56 l-39 -44 -20 18 c-25 23 -98 31 -144 15 -38 -12 -41 -17 -27 -38 6 -9 18 -11 47 -4 41 9 99 1 116 -16 10 -10 -25 -92 -40 -92 -15 0 -103 60 -129 88 -60 65 -80 161 -49 235 24 58 119 126 203 146 54 13 144 13 201 0z m-410 -78 c11 -7 9 -16 -12 -48 -30 -45 -42 -117 -32 -187 8 -49 82 -162 114 -173 9 -3 36 -14 58 -23 l41 -18 -14 -73 c-14 -71 -12 -223 3 -306 l6 -32 -68 -21 c-38 -12 -98 -24 -134 -27 -75 -6 -181 10 -194 31 -7 10 -22 4 -69 -33 -32 -25 -67 -52 -78 -59 l-18 -14 -40 22 -39 23 -87 -69 c-48 -38 -108 -85 -135 -104 -58 -42 -386 -294 -479 -368 -36 -29 -83 -64 -103 -79 l-38 -25 -81 12 c-44 7 -82 18 -84 24 -2 6 1 40 8 76 l12 64 21 6 c12 3 37 16 57 30 29 20 37 32 37 56 l0 32 50 32 c27 18 50 36 50 41 0 5 26 9 57 9 l56 0 44 45 c27 29 51 45 66 45 30 0 99 54 110 86 6 17 16 24 33 24 14 0 40 12 59 27 l34 27 -6 45 -5 46 41 40 41 39 0 42 0 41 73 61 73 62 -10 60 c-12 75 2 166 37 243 55 121 221 244 357 266 73 12 172 13 188 2z m652 -181 c0 -5 -10 -10 -22 -10 -39 0 -85 -36 -98 -75 -14 -41 -6 -61 9 -22 12 31 48 57 78 57 44 0 27 -48 -33 -94 l-43 -33 24 -23 c13 -12 30 -20 37 -17 9 4 7 6 -4 6 -24 1 -23 14 3 37 12 10 32 38 46 62 19 32 28 40 38 32 38 -32 17 -110 -35 -129 -16 -6 -19 -10 -8 -10 32 -2 67 22 83 54 21 46 19 66 -10 110 -27 39 -28 49 -3 40 24 -9 48 -61 48 -103 0 -55 -34 -102 -88 -120 l-42 -14 -42 14 c-102 32 -120 175 -29 231 29 18 91 23 91 7z m-514 -210 c52 -26 52 -37 1 -20 -58 18 -157 117 -157 155 0 3 25 -21 55 -54 31 -33 76 -69 101 -81z"/><path d="M2190 3206 c-135 -31 -257 -117 -316 -222 -69 -124 -54 -226 49 -327 32 -32 61 -57 63 -54 2 2 -8 31 -23 63 -61 133 -19 238 121 305 32 15 80 33 107 40 l48 12 -6 67 c-4 50 -2 74 11 98 9 18 13 32 9 31 -5 0 -33 -6 -63 -13z"/></g></svg>`;
}

/**
 * Logo officiel KeyHome — pastille de marque + clé vectorielle reprise
 * du SVG de marque, suivie du wordmark.
 *
 * Utilisations typiques :
 *   - `<KeyHomeLogo />` en haut des écrans auth (login / register)
 *   - `<KeyHomeLogo variant="stacked" size={48} />` dans le splash
 *   - `<KeyHomeLogo variant="icon" size={20} iconBackground="rgba(255,255,255,0.18)" />`
 *     dans les headers où il faut juste la pastille
 */
export function KeyHomeLogo({
  variant = 'horizontal',
  size = 22,
  color = brand.primary,
  iconBackground = brand.primary,
  markColor = '#FFFFFF',
  showWordmark = true,
}: Props) {
  const pastilleSize = size * 1.7;
  const wordmarkSize = size * 1.0;
  const markSize = size * 1.1;
  const xml = useMemo(() => keyMarkXml(markColor), [markColor]);

  const icon = (
    <YStack
      width={pastilleSize}
      height={pastilleSize}
      borderRadius={pastilleSize * 0.28}
      backgroundColor={iconBackground}
      alignItems="center"
      justifyContent="center"
    >
      <SvgXml xml={xml} width={markSize} height={markSize} />
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
