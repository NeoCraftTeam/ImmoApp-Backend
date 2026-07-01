import { Stack } from 'expo-router';

/**
 * (auth) group layout — modal-style stack for login + register. We use
 * a transparent header + a header-back so each screen feels distinct
 * but the back-arrow is consistent. Screens themselves provide their
 * own titles.
 */
export default function AuthLayout() {
  return (
    <Stack
      screenOptions={{
        headerShown: false,
        animation: 'slide_from_right',
        gestureEnabled: true,
      }}
    />
  );
}
