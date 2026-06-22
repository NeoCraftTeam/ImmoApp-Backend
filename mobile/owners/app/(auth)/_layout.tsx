import { Stack } from 'expo-router';

/**
 * (auth) group layout — stack for login + register + password recovery.
 * Screens provide their own headers / titles.
 */
export default function AuthLayout() {
  return (
    <Stack
      screenOptions={{
        headerShown: false,
        animation: 'slide_from_right',
      }}
    />
  );
}
