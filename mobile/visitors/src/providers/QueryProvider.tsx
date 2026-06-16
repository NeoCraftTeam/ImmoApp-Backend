import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useState, type ReactNode } from 'react';

/**
 * Single QueryClient for the visitor app. Defaults aim for "polite":
 * a 5-minute stale window matches the backend's CDN cache, retries are
 * capped at 2 (so a flaky network doesn't keep firing on a permanent
 * 401), and we don't refetch on window-focus (mobile apps rarely
 * tab-switch like a desktop browser, and aggressive refetch on
 * AppState changes drains battery).
 */
export function QueryProvider({ children }: { children: ReactNode }) {
  const [client] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 5 * 60 * 1000,
            gcTime: 30 * 60 * 1000,
            retry: 2,
            refetchOnWindowFocus: false,
          },
          mutations: {
            retry: 0,
          },
        },
      }),
  );

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
