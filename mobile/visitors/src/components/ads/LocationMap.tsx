import { Bike, Car, Footprints, MapPin, Navigation } from '@tamagui/lucide-icons';
import { useMemo, useState } from 'react';
import { ActivityIndicator, Linking, Platform, Pressable } from 'react-native';
import MapView, { Marker, Polyline, PROVIDER_DEFAULT } from 'react-native-maps';
import { Paragraph, XStack, YStack } from 'tamagui';

import { useDirections, type DirectionsProfile } from '@/hooks/useDirections';
import { useUserLocation } from '@/hooks/useUserLocation';
import { extractRouteCoords, formatDistance, haversineKm, walkingMinutes } from '@/utils/geo';
import { brand } from '@/theme/tokens';

interface Props {
  latitude: number;
  longitude: number;
  quartierName?: string;
  cityName?: string;
  isLocked?: boolean;
}

const HEIGHT = 280;
const FUZZY_OFFSET = 0.0025; // ≈ 270 m at the equator — used for locked ads

/**
 * Property location map — react-native-maps with Apple Maps on iOS
 * (no token / API key needed). For locked ads we offset the marker
 * by ~270 m so the visitor sees the neighborhood without leaking the
 * exact address.
 *
 * When the user has granted location permission we:
 *   1. Drop a second marker on their position,
 *   2. Show a Polyline along the requested directions profile,
 *   3. Display total distance + ETA above the map.
 *
 * Profile switching is free for the user — backend ORS calls are
 * cached 5 min per pair.
 */
export function LocationMap({
  latitude,
  longitude,
  quartierName,
  cityName,
  isLocked,
}: Props) {
  const { location: userLocation, permissionDenied } = useUserLocation();
  const [profile, setProfile] = useState<DirectionsProfile>('driving-car');

  const adCoord = useMemo(() => {
    if (isLocked) {
      // Offset slightly so the exact address isn't leaked
      return {
        latitude: latitude + FUZZY_OFFSET,
        longitude: longitude + FUZZY_OFFSET,
      };
    }
    return { latitude, longitude };
  }, [isLocked, latitude, longitude]);

  const haversine = useMemo(() => {
    if (!userLocation) return null;
    return haversineKm(userLocation, { latitude, longitude });
  }, [userLocation, latitude, longitude]);

  const { data: directions, isFetching } = useDirections({
    fromLat: userLocation?.latitude,
    fromLng: userLocation?.longitude,
    toLat: latitude,
    toLng: longitude,
    profile,
    enabled: Boolean(userLocation) && !isLocked,
  });

  const routeCoords = useMemo(() => extractRouteCoords(directions), [directions]);

  const summary = directions?.data?.summary;

  return (
    <YStack gap={12}>
      <XStack alignItems="center" gap={6}>
        <Paragraph fontSize={17} fontWeight="700" color="$slate900">
          Localisation
        </Paragraph>
        {(quartierName || cityName) && (
          <Paragraph fontSize={13} color="$slate500">
            · {[quartierName, cityName].filter(Boolean).join(', ')}
          </Paragraph>
        )}
      </XStack>

      {userLocation && haversine != null && (
        <DistanceRow
          haversine={haversine}
          summary={summary}
          isFetching={isFetching}
          profile={profile}
        />
      )}

      <YStack
        height={HEIGHT}
        borderRadius={14}
        overflow="hidden"
        backgroundColor="$slate100"
      >
        <MapView
          provider={PROVIDER_DEFAULT}
          style={{ width: '100%', height: '100%' }}
          initialRegion={{
            latitude,
            longitude,
            latitudeDelta: 0.02,
            longitudeDelta: 0.02,
          }}
          showsUserLocation={false}
          showsCompass={false}
        >
          <Marker
            coordinate={adCoord}
            pinColor={brand.primary}
            title={quartierName ?? 'Bien'}
            description={cityName}
          />
          {userLocation && (
            <Marker
              coordinate={userLocation}
              pinColor={brand.info}
              title="Vous êtes ici"
            />
          )}
          {routeCoords && routeCoords.length > 1 && (
            <Polyline
              coordinates={routeCoords}
              strokeWidth={4}
              strokeColor={brand.info}
            />
          )}
        </MapView>
      </YStack>

      {!userLocation && permissionDenied && (
        <YStack
          padding={14}
          gap={10}
          borderRadius={14}
          backgroundColor={brand.primaryAlpha10}
          borderWidth={1}
          borderColor={brand.primaryAlpha20}
        >
          <XStack alignItems="flex-start" gap={10}>
            <YStack
              width={32}
              height={32}
              borderRadius={16}
              alignItems="center"
              justifyContent="center"
              backgroundColor="rgba(255,255,255,0.6)"
            >
              <MapPin size={16} color={brand.primary} />
            </YStack>
            <YStack flex={1} gap={2}>
              <Paragraph fontSize={13.5} fontWeight="800" color="$slate900">
                Voir la distance jusqu'à ce bien
              </Paragraph>
              <Paragraph fontSize={12} color="$slate700" lineHeight={17}>
                Activez la localisation pour voir l'itinéraire en voiture, à pied
                ou à vélo depuis votre position actuelle.
              </Paragraph>
            </YStack>
          </XStack>
          <Pressable
            onPress={() => void Linking.openSettings()}
            hitSlop={6}
            accessibilityRole="button"
            accessibilityLabel="Ouvrir les réglages pour activer la localisation"
          >
            <XStack
              alignItems="center"
              justifyContent="center"
              gap={6}
              paddingVertical={9}
              borderRadius={10}
              backgroundColor={brand.primary}
            >
              <Navigation size={14} color="white" />
              <Paragraph fontSize={13} fontWeight="800" color="white">
                Activer la localisation
              </Paragraph>
            </XStack>
          </Pressable>
        </YStack>
      )}

      {userLocation && !isLocked && (
        <YStack gap={8}>
          <XStack gap={8} flexWrap="wrap">
            <ProfileChip
              active={profile === 'driving-car'}
              label="Voiture"
              icon={<Car size={15} color={profile === 'driving-car' ? 'white' : brand.slate700} />}
              onPress={() => setProfile('driving-car')}
            />
            <ProfileChip
              active={profile === 'foot-walking'}
              label="À pied"
              icon={<Footprints size={15} color={profile === 'foot-walking' ? 'white' : brand.slate700} />}
              onPress={() => setProfile('foot-walking')}
            />
            <ProfileChip
              active={profile === 'cycling-regular'}
              label="Vélo"
              icon={<Bike size={15} color={profile === 'cycling-regular' ? 'white' : brand.slate700} />}
              onPress={() => setProfile('cycling-regular')}
            />
          </XStack>

          <Pressable
            onPress={() => {
              const url = Platform.select({
                ios: `http://maps.apple.com/?daddr=${latitude},${longitude}&saddr=Current%20Location`,
                android: `https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}`,
              });
              if (url) void Linking.openURL(url);
            }}
            hitSlop={6}
            accessibilityRole="button"
          >
            <XStack
              alignItems="center"
              gap={6}
              paddingHorizontal={14}
              paddingVertical={10}
              borderRadius={12}
              backgroundColor={brand.primary}
            >
              <Navigation size={16} color="white" />
              <Paragraph fontSize={14} fontWeight="700" color="white" flex={1}>
                Itinéraire dans Maps
              </Paragraph>
            </XStack>
          </Pressable>
        </YStack>
      )}
    </YStack>
  );
}

function DistanceRow({
  haversine,
  summary,
  isFetching,
  profile,
}: {
  haversine: number;
  summary?: { distance_m: number; duration_s: number };
  isFetching: boolean;
  profile: DirectionsProfile;
}) {
  const distanceLabel = summary
    ? formatDistance(summary.distance_m / 1000)
    : formatDistance(haversine);
  const durationMinutes = summary
    ? Math.max(1, Math.round(summary.duration_s / 60))
    : profile === 'foot-walking'
      ? walkingMinutes(haversine)
      : null;

  return (
    <XStack
      alignItems="center"
      gap={10}
      paddingHorizontal={12}
      paddingVertical={10}
      borderRadius={10}
      backgroundColor="$slate100"
    >
      <MapPin size={16} color="$slate700" />
      <Paragraph fontSize={13} color="$slate700" flex={1}>
        À <Paragraph fontSize={13} fontWeight="700" color="$slate900">
          {distanceLabel}
        </Paragraph>
        {durationMinutes ? ` · environ ${durationMinutes} min` : ''}
        {summary ? ' (itinéraire réel)' : ' (à vol d\'oiseau)'}
      </Paragraph>
      {isFetching ? <ActivityIndicator size="small" /> : null}
    </XStack>
  );
}

function ProfileChip({
  active,
  label,
  icon,
  onPress,
}: {
  active: boolean;
  label: string;
  icon: React.ReactNode;
  onPress: () => void;
}) {
  return (
    <Pressable onPress={onPress} hitSlop={4}>
      <XStack
        alignItems="center"
        gap={6}
        paddingHorizontal={12}
        paddingVertical={8}
        borderRadius={999}
        backgroundColor={active ? brand.slate900 : '$slate100'}
        borderWidth={active ? 0 : 1}
        borderColor="$slate300"
      >
        {icon}
        <Paragraph
          fontSize={12.5}
          fontWeight="700"
          color={active ? 'white' : '$slate700'}
        >
          {label}
        </Paragraph>
      </XStack>
    </Pressable>
  );
}
