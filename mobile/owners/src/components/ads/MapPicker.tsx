import { MapPin } from '@tamagui/lucide-icons';
import { useState } from 'react';
import MapView, { Marker, type MapPressEvent, type Region } from 'react-native-maps';
import { Paragraph, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';

/**
 * Tap-to-place location picker for the ad form. Defaults to a central
 * West/Central-Africa region (Yaoundé) when no coordinates are set yet.
 * Tapping the map (or dragging the marker) reports the new lat/lng.
 */
const DEFAULT_REGION: Region = {
  latitude: 3.848,
  longitude: 11.5021,
  latitudeDelta: 0.08,
  longitudeDelta: 0.08,
};

export function MapPicker({
  latitude,
  longitude,
  onChange,
}: {
  latitude?: number | null;
  longitude?: number | null;
  onChange: (coords: { latitude: number; longitude: number }) => void;
}) {
  const hasPin = latitude != null && longitude != null;
  const [region] = useState<Region>(
    hasPin
      ? { latitude: latitude as number, longitude: longitude as number, latitudeDelta: 0.02, longitudeDelta: 0.02 }
      : DEFAULT_REGION,
  );

  const handlePress = (e: MapPressEvent) => {
    onChange(e.nativeEvent.coordinate);
  };

  return (
    <YStack gap={6}>
      <YStack height={200} borderRadius={14} overflow="hidden" borderWidth={1} borderColor="$slate300">
        <MapView style={{ flex: 1 }} initialRegion={region} onPress={handlePress}>
          {hasPin ? (
            <Marker
              coordinate={{ latitude: latitude as number, longitude: longitude as number }}
              draggable
              onDragEnd={(e) => onChange(e.nativeEvent.coordinate)}
              pinColor={brand.primary}
            />
          ) : null}
        </MapView>
      </YStack>
      <YStack flexDirection="row" alignItems="center" gap={6}>
        <MapPin size={14} color={brand.slate500} />
        <Paragraph fontSize={12} color="$slate500">
          {hasPin
            ? `${(latitude as number).toFixed(5)}, ${(longitude as number).toFixed(5)}`
            : 'Touchez la carte pour positionner le bien'}
        </Paragraph>
      </YStack>
    </YStack>
  );
}
