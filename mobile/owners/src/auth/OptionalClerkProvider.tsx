import { ClerkProvider } from '@clerk/clerk-expo';
import { tokenCache } from '@clerk/clerk-expo/token-cache';
import type { ReactNode } from 'react';

import { resolveClerkPublishableKey, useAuthPublicConfig } from '@/hooks/useAuthPublicConfig';

interface Props {
  children: ReactNode;
}

/**
 * Wraps the owner app with Clerk when a publishable key is available (env or API).
 * Required for social SSO (Google / Facebook / GitHub) — same provider as the web PWA
 * and the visitor app. When no key is available the app renders unchanged (Clerk
 * buttons are hidden and email/password login stays available).
 */
export function OptionalClerkProvider({ children }: Props) {
  const { data: authConfig } = useAuthPublicConfig();
  const publishableKey = resolveClerkPublishableKey(authConfig);

  if (!publishableKey) {
    return children;
  }

  return (
    <ClerkProvider publishableKey={publishableKey} tokenCache={tokenCache}>
      {children}
    </ClerkProvider>
  );
}
