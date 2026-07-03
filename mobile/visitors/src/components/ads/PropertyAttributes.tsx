import {
  AirVent,
  Armchair,
  Bath,
  Building2,
  CheckCircle2,
  Dumbbell,
  Eye,
  Flame,
  Snowflake,
  Sofa,
  Trees,
  Tv,
  Utensils,
  Waves,
  Wifi,
  Wind,
} from '@tamagui/lucide-icons';
import { Paragraph, XStack, YStack } from 'tamagui';

import { usePropertyAttributes } from '@/hooks/usePropertyAttributes';
import { brand } from '@/theme/tokens';

interface Props {
  attributes: string[];
}

/**
 * Best-effort key → lucide icon map. Covers the common immo
 * equipment list (wifi, climatisation, ascenseur, piscine, etc.);
 * any unknown key falls back to a checkmark.
 */
const ICONS: Record<string, React.ComponentType<{ size?: number; color?: string }>> = {
  wifi: Wifi,
  internet: Wifi,
  climatisation: Snowflake,
  clim: Snowflake,
  air_conditionne: Snowflake,
  chauffage: Flame,
  ventilation: Wind,
  ventilateur: Wind,
  cuisine: Utensils,
  cuisine_equipee: Utensils,
  meuble: Sofa,
  meublé: Sofa,
  ascenseur: Building2,
  piscine: Waves,
  jardin: Trees,
  terrasse: Trees,
  balcon: Trees,
  salle_de_sport: Dumbbell,
  gym: Dumbbell,
  tele: Tv,
  tv: Tv,
  baignoire: Bath,
  vue: Eye,
  air: AirVent,
  mobilier: Armchair,
};

function pickIcon(key: string) {
  const norm = key.toLowerCase().replace(/[\s-]/g, '_');
  return ICONS[norm] ?? CheckCircle2;
}

function humanize(key: string): string {
  const norm = key.replace(/[_-]/g, ' ').trim();
  return norm.charAt(0).toUpperCase() + norm.slice(1);
}

/**
 * Equipment list — rendered as a 2-column outlined chip grid,
 * mirroring the `list` variant on the web `PropertyAttributes`. The
 * `usePropertyAttributes` hook supplies a key → metadata map; an
 * unknown key still renders cleanly via `humanize()` + a generic
 * check icon (no broken UI on a new backend attribute).
 */
export function PropertyAttributes({ attributes }: Props) {
  const { data: meta } = usePropertyAttributes();

  if (!attributes || attributes.length === 0) {
    return null;
  }

  return (
    <YStack gap={12}>
      <Paragraph fontSize={17} fontWeight="700" color="$slate900">
        Équipements
      </Paragraph>
      <YStack gap={10}>
        {attributes.map((key) => {
          const Icon = pickIcon(key);
          const label = meta?.[key]?.label ?? humanize(key);
          return (
            <XStack
              key={key}
              alignItems="center"
              gap={10}
              paddingVertical={10}
              paddingHorizontal={14}
              borderRadius={12}
              backgroundColor="$slate100"
            >
              <Icon size={18} color="$slate700" />
              <Paragraph fontSize={14} color="$slate900" fontWeight="500" flex={1}>
                {label}
              </Paragraph>
            </XStack>
          );
        })}
      </YStack>
    </YStack>
  );
}
