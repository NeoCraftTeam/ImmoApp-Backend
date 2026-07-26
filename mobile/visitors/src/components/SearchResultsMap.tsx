import { useRouter } from 'expo-router';
import { useEffect, useMemo, useRef } from 'react';
import { Platform } from 'react-native';
import MapView, { Marker, PROVIDER_DEFAULT } from 'react-native-maps';
import { Paragraph, XStack, YStack } from 'tamagui';

import { useCurrency } from '@/hooks/useCurrency';
import { useMotionPresets } from '@/hooks/useMotionPresets';
import { brand } from '@/theme/tokens';
import type { Ad } from '@/types/ad';

interface Props {
  ads: Ad[];
}

/**
 * Carte des résultats de recherche — pendant mobile du toggle liste/carte
 * du web. `PROVIDER_DEFAULT` = Apple Maps (MapKit) sur iOS et Google Maps
 * sur Android, comme demandé (carte native de la plateforme).
 *
 * Chaque annonce géolocalisée est rendue en « pastille prix » façon
 * Airbnb/web plutôt qu'en épingle générique : le prix est l'information
 * décisive à ce niveau de zoom. `tracksViewChanges=false` fige le rendu
 * des markers custom après le premier draw — sans ça, chaque frame
 * re-rasterise toutes les pastilles et la carte rame dès ~20 annonces.
 * Le callout (titre + prix) ouvre le détail. La caméra se ré-ajuste à
 * chaque changement du jeu de résultats.
 */
export function SearchResultsMap({ ads }: Props) {
  const router = useRouter();
  const { format } = useCurrency();
  const { scrollAnimated } = useMotionPresets();
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
        animated: scrollAnimated,
      },
    );
  }, [located, scrollAnimated]);

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
      showsUserLocation
      showsMyLocationButton={false}
      showsCompass={false}
    >
      {located.map((ad) => (
        <Marker
          key={ad.id}
          coordinate={ad.location}
          title={ad.title}
          description={ad.price != null ? format(ad.price) : undefined}
          tracksViewChanges={false}
          anchor={{ x: 0.5, y: 1 }}
          onCalloutPress={() =>
            router.push({
              pathname: '/ads/[slug]',
              params: { slug: ad.id },
            })
          }
        >
          <YStack alignItems="center">
            <XStack
              paddingHorizontal={10}
              paddingVertical={5}
              borderRadius={999}
              backgroundColor={brand.primary}
              // Ombre légère pour détacher la pastille du fond de carte.
              style={
                Platform.OS === 'ios'
                  ? {
                      shadowColor: '#000',
                      shadowOpacity: 0.25,
                      shadowRadius: 3,
                      shadowOffset: { width: 0, height: 1 },
                    }
                  : { elevation: 3 }
              }
            >
              <Paragraph fontSize={12} fontWeight="800" color="white" numberOfLines={1}>
                {ad.price != null ? format(ad.price) : '—'}
              </Paragraph>
            </XStack>
            {/* Petite pointe sous la pastille, ancrée sur la coordonnée. */}
            <YStack
              width={0}
              height={0}
              borderLeftWidth={5}
              borderRightWidth={5}
              borderTopWidth={6}
              borderLeftColor="transparent"
              borderRightColor="transparent"
              borderTopColor={brand.primary}
            />
          </YStack>
        </Marker>
      ))}
    </MapView>
  );
}
