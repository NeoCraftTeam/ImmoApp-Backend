import { Text, View, type StyleProp, type ViewStyle } from 'react-native';

import { brand } from '@/theme/tokens';

interface Props {
  /** Valeur numérique ; au-delà de `max` on affiche `max+`. */
  count: number;
  max?: number;
  /** Diamètre du badge (px). Le texte scale dessus. */
  size?: number;
  color?: string;
  textColor?: string;
  style?: StyleProp<ViewStyle>;
}

/**
 * Pastille de compteur (notifications, non-lus, filtres actifs…).
 *
 * Rendu en React Native pur pour un centrage PARFAIT : le bug récurrent
 * venait du `lineHeight` par défaut de `Paragraph` (Tamagui) qui dépasse
 * la hauteur fixe du badge et décale/coupe le chiffre. Ici `lineHeight`
 * est explicitement égal à la taille de police et le texte est centré
 * dans un carré `size×size` (borderRadius = size/2 → cercle).
 */
export function CountBadge({
  count,
  max = 99,
  size = 18,
  color = brand.primary,
  textColor = '#FFFFFF',
  style,
}: Props) {
  if (count <= 0) {
    return null;
  }
  const label = count > max ? `${max}+` : String(count);
  const fontSize = label.length > 2 ? size * 0.5 : size * 0.58;

  return (
    <View
      style={[
        {
          minWidth: size,
          height: size,
          paddingHorizontal: label.length > 1 ? 5 : 0,
          borderRadius: size / 2,
          backgroundColor: color,
          alignItems: 'center',
          justifyContent: 'center',
        },
        style,
      ]}
    >
      <Text
        style={{
          fontSize,
          lineHeight: fontSize + 1,
          fontWeight: '800',
          color: textColor,
          textAlign: 'center',
          includeFontPadding: false,
        }}
        allowFontScaling={false}
      >
        {label}
      </Text>
    </View>
  );
}
