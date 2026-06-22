import * as Location from 'expo-location';
import { useEffect, useState } from 'react';

export interface UserCoords {
  latitude: number;
  longitude: number;
  accuracy?: number | null;
}

interface State {
  location: UserCoords | null;
  permissionDenied: boolean;
  error: Error | null;
  isLoading: boolean;
}

/**
 * One-shot user-location hook. Asks for foreground-location permission
 * and reads the device's coarse position via `expo-location`.
 *
 * We deliberately do NOT use `watchPositionAsync` here — the detail
 * screen only needs the user's coordinates ONCE to draw a line on the
 * map and compute distance. Watching would drain battery for no UX
 * benefit on a detail screen.
 *
 * Permission denial is a normal outcome (the user is browsing
 * anonymously, they may not have granted location). The caller falls
 * back to "no distance shown" rather than blocking the screen.
 */
export function useUserLocation(): State {
  const [state, setState] = useState<State>({
    location: null,
    permissionDenied: false,
    error: null,
    isLoading: true,
  });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const { status } = await Location.requestForegroundPermissionsAsync();
        if (cancelled) return;
        if (status !== 'granted') {
          setState({
            location: null,
            permissionDenied: true,
            error: null,
            isLoading: false,
          });
          return;
        }
        const pos = await Location.getCurrentPositionAsync({
          accuracy: Location.Accuracy.Balanced,
        });
        if (cancelled) return;
        setState({
          location: {
            latitude: pos.coords.latitude,
            longitude: pos.coords.longitude,
            accuracy: pos.coords.accuracy,
          },
          permissionDenied: false,
          error: null,
          isLoading: false,
        });
      } catch (err) {
        if (cancelled) return;
        setState({
          location: null,
          permissionDenied: false,
          error: err as Error,
          isLoading: false,
        });
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  return state;
}
