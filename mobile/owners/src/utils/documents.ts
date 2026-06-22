import Constants from 'expo-constants';
import * as FileSystem from 'expo-file-system/legacy';
import * as Print from 'expo-print';
import * as SecureStore from 'expo-secure-store';
import * as Sharing from 'expo-sharing';

import { SESSION_KEY } from '@/auth/storage-keys';

/**
 * Marketing-document helpers (pancartes / placardes, business cards, QR
 * PDFs). These endpoints return binary PDFs behind Sanctum auth, so we
 * can't just open the URL — we download with the bearer header into the
 * app cache, then hand the local file to the OS print / share sheet.
 */

function resolveBaseUrl(): string {
  const envUrl = process.env.EXPO_PUBLIC_API_BASE_URL;
  if (envUrl && envUrl.trim() !== '') return envUrl.trim();
  const extra = (Constants.expoConfig?.extra ?? {}) as {
    apiBaseUrl?: string;
    apiBaseUrlDev?: string;
  };
  if (__DEV__ && extra.apiBaseUrlDev) return extra.apiBaseUrlDev;
  return extra.apiBaseUrl ?? 'https://api.keyhome.app/api/v1';
}

/**
 * Download an authenticated file (`path` relative to the API base) into
 * the cache directory and return its local URI. Throws on non-2xx so the
 * caller can surface a French error.
 */
export async function downloadAuthedFile(
  path: string,
  filename: string,
): Promise<string> {
  const token = await SecureStore.getItemAsync(SESSION_KEY).catch(() => null);
  const url = `${resolveBaseUrl()}${path}`;
  const target = `${FileSystem.cacheDirectory ?? ''}${filename}`;
  const result = await FileSystem.downloadAsync(url, target, {
    headers: token ? { Authorization: `Bearer ${token}`, Accept: 'application/pdf' } : {},
  });
  if (result.status < 200 || result.status >= 300) {
    throw new Error(`Téléchargement impossible (HTTP ${result.status}).`);
  }
  return result.uri;
}

/** Open the OS share sheet for a local file (also exposes "Save"/"Print"). */
export async function shareLocalFile(uri: string): Promise<void> {
  const available = await Sharing.isAvailableAsync();
  if (!available) {
    throw new Error('Le partage n’est pas disponible sur cet appareil.');
  }
  await Sharing.shareAsync(uri, { mimeType: 'application/pdf', UTI: 'com.adobe.pdf' });
}

/** Send a local PDF to the OS print dialog. */
export async function printLocalFile(uri: string): Promise<void> {
  await Print.printAsync({ uri });
}
