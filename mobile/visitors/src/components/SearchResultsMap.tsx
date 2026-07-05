import { useRouter } from 'expo-router';
import { useEffect, useMemo, useRef } from 'react';
import MapView, { Marker, PROVIDER_DEFAULT } from 'react-native-maps';
import { Paragraph, YStack } from 'tamagui';

import { useCurrency } from '@/hooks/useCurrency';
import { brand } from '@/theme/tokens';
import type { Ad } from '@/types/ad';

interface Props {
  ads: Ad[];
}

/**
 * Map view of the search results — mobile counterpart of the web's
 * list/map toggle (Mapbox there, react-native-maps here, same as the
 * ad-detail `LocationMap`). One pin per geolocated ad; the callout
 * (title + price) opens the ad detail. The camera re-fits whenever
 * the result set changes.
 */
export function SearchResultsMap({ ads }: Props) {
  const router = useRouter();
  const { format } = useCurrency();
  const mapRef = useRef<MapView | null>(null);

  const located = useMemo(
    () =>
      ads.filter(
        (ad): ad is Ad & { location: NonNullable<Ad['location']> } =>
          ad.location != null &&
          typeof ad.location.latitude === 'number' &&
          typeof ad.location.longitude === 'number',
      ),
    [ads],
  );

  useEffect(() => {
    if (located.length === 0) return;
    mapRef.current?.fitToCoordinates(
      located.map((ad) => ad.location),
      {
        edgePadding: { top: 60, right: 60, bottom: 60, left: 60 },
        animated: true,
      },
    );
  }, [located]);

  const first = located[0];
  if (!first) {
    return (
      <YStack flex={1} alignItems="center" justifyContent="center" padding="$5">
        <Paragraph color="$slate500" fontSize={14} textAlign="center">
          Aucun résultat géolocalisé à afficher sur la carte.
        </Paragraph>
      </YStack>
    );
  }

  return (
    <MapView
      ref={mapRef}
      provider={PROVIDER_DEFAULT}
      style={{ flex: 1 }}
      initialRegion={{
        latitude: first.location.latitude,
        longitude: first.location.longitude,
        latitudeDelta: 0.1,
        longitudeDelta: 0.1,
      }}
      showsUserLocation={false}
      showsCompass={false}
    >
      {located.map((ad) => (
        <Marker
          key={ad.id}
          coordinate={ad.location}
          pinColor={brand.primary}
          title={ad.title}
          description={ad.price != null ? format(ad.price) : undefined}
          onCalloutPress={() =>
            router.push({
              pathname: '/ads/[slug]',
              params: { slug: ad.id },
            })
          }
        />
      ))}
    </MapView>
  );
}
