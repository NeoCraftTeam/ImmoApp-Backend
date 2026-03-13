import * as AuthSession from 'expo-auth-session';
import * as SecureStore from 'expo-secure-store';
import * as WebBrowser from 'expo-web-browser';
import { Platform } from 'react-native';

import { APP_CONFIG, GOOGLE_CONFIG } from '../config';
import type { BackendAuthResult, GoogleUserInfo, OAuthResult, WebViewRef } from '../types';

WebBrowser.maybeCompleteAuthSession();

const GOOGLE_DISCOVERY = {
  authorizationEndpoint: 'https://accounts.google.com/o/oauth2/v2/auth',
  tokenEndpoint: 'https://oauth2.googleapis.com/token',
  revocationEndpoint: 'https://oauth2.googleapis.com/revoke',
};

const AUTH_TOKEN_KEY = 'auth_token';

class OAuthService {
  private webViewRef: WebViewRef | null = null;

  initialize(webViewRef: WebViewRef): void {
    this.webViewRef = webViewRef;
  }

  cleanup(): void {
    this.webViewRef = null;
  }

  private sendToWebView(type: string, data: Record<string, unknown>): void {
    this.webViewRef?.current?.postMessage(JSON.stringify({ type, data }));
  }

  private getNativeClientId(): string {
    if (Platform.OS === 'ios') return GOOGLE_CONFIG.clientIdIos;
    if (Platform.OS === 'android') return GOOGLE_CONFIG.clientIdAndroid;
    return GOOGLE_CONFIG.clientIdWeb;
  }

  async signInWithGoogle(): Promise<OAuthResult> {
    try {
      const nativeClientId = this.getNativeClientId();

      const request = new AuthSession.AuthRequest({
        clientId: nativeClientId,
        scopes: ['profile', 'email'],
        redirectUri: AuthSession.makeRedirectUri({
          scheme: APP_CONFIG.scheme,
          path: 'oauth/callback',
        }),
        usePKCE: true,
      });

      await request.makeAuthUrlAsync(GOOGLE_DISCOVERY);
      const result = await request.promptAsync(GOOGLE_DISCOVERY);

      if (result.type === 'success') {
        const tokenResponse = await AuthSession.exchangeCodeAsync(
          {
            clientId: GOOGLE_CONFIG.clientIdWeb,
            code: result.params.code,
            extraParams: { code_verifier: request.codeVerifier ?? '' },
            redirectUri: request.redirectUri,
          },
          GOOGLE_DISCOVERY,
        );

        const userInfo = await this.fetchGoogleUserInfo(tokenResponse.accessToken);

        return {
          success: true,
          provider: 'google',
          accessToken: tokenResponse.accessToken,
          idToken: tokenResponse.idToken ?? undefined,
          user: userInfo,
        };
      }

      if (result.type === 'cancel' || result.type === 'dismiss') {
        return { success: false, error: 'cancelled', message: "Connexion annulée par l'utilisateur" };
      }

      return { success: false, error: 'failed', message: 'Échec de la connexion Google' };
    } catch (error) {
      const msg = error instanceof Error ? error.message : 'Une erreur est survenue';
      return { success: false, error: 'error', message: msg };
    }
  }

  private async fetchGoogleUserInfo(accessToken: string): Promise<GoogleUserInfo> {
    const response = await fetch('https://www.googleapis.com/userinfo/v2/me', {
      headers: { Authorization: `Bearer ${accessToken}` },
    });

    if (!response.ok) {
      throw new Error('Impossible de récupérer les informations Google');
    }

    return response.json() as Promise<GoogleUserInfo>;
  }

  /**
   * Authenticate with the Laravel backend.
   * POST /api/v1/auth/oauth/google with { token, id_token }
   */
  async authenticateWithBackend(oAuthResult: OAuthResult): Promise<BackendAuthResult> {
    try {
      const response = await fetch(`${APP_CONFIG.apiBaseUrl}/api/v1/auth/oauth/${oAuthResult.provider}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          token: oAuthResult.accessToken,
          id_token: oAuthResult.idToken ?? null,
        }),
      });

      const data = await response.json();

      if (response.ok && data.token) {
        await this.storeAuthToken(data.token);
        return { success: true, user: data.user, token: data.token };
      }

      return { success: false, error: data.message ?? 'Échec de l\'authentification', status: response.status };
    } catch (error) {
      const msg = error instanceof Error ? error.message : 'Erreur de connexion au serveur';
      return { success: false, error: msg };
    }
  }

  async performOAuthFlow(provider = 'google'): Promise<void> {
    this.sendToWebView('OAUTH_STARTED', { provider });

    if (provider !== 'google') {
      this.sendToWebView('OAUTH_ERROR', {
        error: 'unsupported_provider',
        message: `Provider "${provider}" non supporté sur mobile`,
      });
      return;
    }

    const oAuthResult = await this.signInWithGoogle();

    if (!oAuthResult.success) {
      this.sendToWebView('OAUTH_CANCELLED', oAuthResult as unknown as Record<string, unknown>);
      return;
    }

    const backendResult = await this.authenticateWithBackend(oAuthResult);

    if (backendResult.success) {
      // Token is already stored in SecureStore by authenticateWithBackend.
      // Inject it directly into the WebView session instead of sending via postMessage.
      if (backendResult.token) {
        this.webViewRef?.current?.injectJavaScript(`
          (function() {
            var _origFetch = window.fetch;
            window.fetch = function(input, init) {
              init = init || {};
              init.headers = init.headers || {};
              if (!init.headers['Authorization']) {
                init.headers['Authorization'] = 'Bearer ${backendResult.token}';
              }
              return _origFetch.call(this, input, init);
            };
            window.location.reload();
            true;
          })();
        `);
      }
      this.sendToWebView('OAUTH_SUCCESS', { user: backendResult.user });
    } else {
      this.sendToWebView('OAUTH_ERROR', backendResult as unknown as Record<string, unknown>);
    }
  }

  async handleOAuthRequest(data: Record<string, unknown>): Promise<void> {
    const provider = (data?.provider as string) || 'google';
    await this.performOAuthFlow(provider);
  }

  async storeAuthToken(token: string): Promise<void> {
    await SecureStore.setItemAsync(AUTH_TOKEN_KEY, token);
  }

  async getAuthToken(): Promise<string | null> {
    return SecureStore.getItemAsync(AUTH_TOKEN_KEY);
  }

  async clearAuthToken(): Promise<void> {
    await SecureStore.deleteItemAsync(AUTH_TOKEN_KEY);
  }

  async isAuthenticated(): Promise<boolean> {
    const token = await this.getAuthToken();
    return !!token;
  }
}

export default new OAuthService();
