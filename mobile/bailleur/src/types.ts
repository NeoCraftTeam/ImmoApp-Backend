import type { RefObject } from 'react';
import type WebView from 'react-native-webview';

export type WebViewRef = RefObject<WebView | null>;

export interface BridgeMessage {
  type: string;
  data: Record<string, unknown>;
}

export interface ImageResult {
  uri: string;
  width: number;
  height: number;
  mimeType: string;
  fileName: string;
}

export interface LocationResult {
  latitude: number;
  longitude: number;
  accuracy: number | null;
  altitude: number | null;
}

export interface OAuthResult {
  success: boolean;
  provider?: string;
  accessToken?: string;
  idToken?: string;
  user?: GoogleUserInfo;
  error?: string;
  message?: string;
}

export interface GoogleUserInfo {
  id: string;
  email: string;
  name: string;
  given_name?: string;
  family_name?: string;
  picture?: string;
}

export interface BackendAuthResult {
  success: boolean;
  user?: Record<string, unknown>;
  token?: string;
  error?: string;
  status?: number;
}

export type HapticStyle = 'light' | 'medium' | 'heavy' | 'success' | 'error' | 'warning';

export type StatusBarStyle = 'dark' | 'light' | 'auto';

export interface DeepLinkData {
  url?: string;
  adId?: string;
  screen?: string;
}
