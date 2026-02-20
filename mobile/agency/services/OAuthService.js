import * as AuthSession from 'expo-auth-session';
import * as SecureStore from 'expo-secure-store';
import * as WebBrowser from 'expo-web-browser';
import { Platform } from 'react-native';

// Required for web browser auth session completion on Android
WebBrowser.maybeCompleteAuthSession();

/**
 * OAuthService — Native Google Sign-In pour KeyHome Agency
 * Compatible avec Filament-Socialite backend.
 *
 * FIX #6 : utilise androidClientId / iosClientId selon Platform.OS
 * (le webClientId cause un redirect_uri_mismatch sur les apps natives).
 */
class OAuthService {
  constructor() {
    this.webViewRef = null;
    this.config = {
      googleClientIdAndroid: process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID_ANDROID,
      googleClientIdIos:     process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID_IOS,
      googleClientIdWeb:     process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID_WEB,
      apiBaseUrl: process.env.EXPO_PUBLIC_BASE_URL || 'https://api.keyhome.neocraft.dev',
    };
  }

  initialize(webViewRef) {
    this.webViewRef = webViewRef;
  }

  sendToWebView(type, data) {
    if (this.webViewRef?.current) {
      this.webViewRef.current.postMessage(JSON.stringify({ type, data }));
    }
  }

  /** Retourne le clientId approprié selon la plateforme */
  _getNativeClientId() {
    if (Platform.OS === 'ios')     return this.config.googleClientIdIos;
    if (Platform.OS === 'android') return this.config.googleClientIdAndroid;
    return this.config.googleClientIdWeb; // fallback web
  }

  getGoogleConfig() {
    return {
      androidClientId: this.config.googleClientIdAndroid,
      iosClientId:     this.config.googleClientIdIos,
      webClientId:     this.config.googleClientIdWeb,
      scopes: ['profile', 'email'],
    };
  }

  async signInWithGoogle() {
    try {
      const discovery = {
        authorizationEndpoint: 'https://accounts.google.com/o/oauth2/v2/auth',
        tokenEndpoint:         'https://oauth2.googleapis.com/token',
        revocationEndpoint:    'https://oauth2.googleapis.com/revoke',
      };

      // FIX #6 : clientId natif (pas webClientId)
      const nativeClientId = this._getNativeClientId();

      const request = new AuthSession.AuthRequest({
        clientId: nativeClientId,
        scopes: ['profile', 'email'],
        redirectUri: AuthSession.makeRedirectUri({
          scheme: 'keyhome-agency',
          path:   'oauth/callback',
        }),
        usePKCE: true,
      });

      await request.makeAuthUrlAsync(discovery);
      
      const result = await request.promptAsync(discovery);

      if (result.type === 'success') {
        // Exchange code for tokens
        const tokenResponse = await AuthSession.exchangeCodeAsync(
          {
            clientId: config.webClientId,
            code: result.params.code,
            extraParams: {
              code_verifier: request.codeVerifier,
            },
            redirectUri: request.redirectUri,
          },
          discovery
        );

        // Get user info from Google
        const userInfo = await this.fetchGoogleUserInfo(tokenResponse.accessToken);

        return {
          success: true,
          provider: 'google',
          accessToken: tokenResponse.accessToken,
          idToken: tokenResponse.idToken,
          user: userInfo,
        };
      } else if (result.type === 'cancel') {
        return {
          success: false,
          error: 'cancelled',
          message: 'Connexion annulée par l\'utilisateur',
        };
      } else {
        return {
          success: false,
          error: 'failed',
          message: 'Échec de la connexion Google',
        };
      }
    } catch (error) {
      console.error('Google Sign-In error:', error);
      return {
        success: false,
        error: 'error',
        message: error.message || 'Une erreur est survenue',
      };
    }
  }

  /**
   * Fetch Google user info using access token
   */
  async fetchGoogleUserInfo(accessToken) {
    try {
      const response = await fetch('https://www.googleapis.com/userinfo/v2/me', {
        headers: {
          Authorization: `Bearer ${accessToken}`,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch user info');
      }

      return await response.json();
    } catch (error) {
      console.error('Error fetching Google user info:', error);
      throw error;
    }
  }

  /**
   * Authenticate with our backend using the OAuth result
   * This creates or logs in the user via our API
   */
  async authenticateWithBackend(oAuthResult, panelType = 'agency') {
    try {
      const response = await fetch(`${this.config.apiBaseUrl}/api/v1/auth/social/authenticate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          provider: oAuthResult.provider,
          access_token: oAuthResult.accessToken,
          id_token: oAuthResult.idToken,
          device_name: `keyhome-${panelType}-mobile`,
        }),
      });

      const data = await response.json();

      if (response.ok) {
        // Store the auth token securely
        if (data.token) {
          await this.storeAuthToken(data.token);
        }

        return {
          success: true,
          user: data.user,
          token: data.token,
        };
      } else {
        return {
          success: false,
          error: data.message || 'Échec de l\'authentification',
          status: response.status,
        };
      }
    } catch (error) {
      console.error('Backend authentication error:', error);
      return {
        success: false,
        error: error.message || 'Erreur de connexion au serveur',
      };
    }
  }

  /**
   * Complete OAuth flow: native sign-in + backend authentication
   */
  async performOAuthFlow(provider = 'google', panelType = 'agency') {
    try {
      this.sendToWebView('OAUTH_STARTED', { provider });

      let oAuthResult;

      if (provider === 'google') {
        oAuthResult = await this.signInWithGoogle();
      } else {
        this.sendToWebView('OAUTH_ERROR', {
          error: 'unsupported_provider',
          message: `Provider "${provider}" non supporté sur mobile`,
        });
        return;
      }

      if (!oAuthResult.success) {
        this.sendToWebView('OAUTH_CANCELLED', oAuthResult);
        return;
      }

      // Authenticate with our backend
      const backendResult = await this.authenticateWithBackend(oAuthResult, panelType);

      if (backendResult.success) {
        this.sendToWebView('OAUTH_SUCCESS', {
          user: backendResult.user,
          token: backendResult.token,
        });
      } else {
        this.sendToWebView('OAUTH_ERROR', backendResult);
      }
    } catch (error) {
      console.error('OAuth flow error:', error);
      this.sendToWebView('OAUTH_ERROR', {
        error: 'unknown',
        message: error.message || 'Une erreur inattendue est survenue',
      });
    }
  }

  /**
   * Store auth token securely
   */
  async storeAuthToken(token) {
    try {
      await SecureStore.setItemAsync('auth_token', token);
    } catch (error) {
      console.error('Error storing auth token:', error);
    }
  }

  /**
   * Get stored auth token
   */
  async getAuthToken() {
    try {
      return await SecureStore.getItemAsync('auth_token');
    } catch (error) {
      console.error('Error getting auth token:', error);
      return null;
    }
  }

  /**
   * Remove stored auth token (logout)
   */
  async clearAuthToken() {
    try {
      await SecureStore.deleteItemAsync('auth_token');
    } catch (error) {
      console.error('Error clearing auth token:', error);
    }
  }

  /**
   * Check if user is authenticated
   */
  async isAuthenticated() {
    const token = await this.getAuthToken();
    return !!token;
  }

  /**
   * Handle OAuth request from WebView
   */
  async handleOAuthRequest(data) {
    const { provider, panelType } = data || {};
    await this.performOAuthFlow(provider || 'google', panelType || 'agency');
  }

  /**
   * Cleanup resources
   */
  cleanup() {
    this.webViewRef = null;
  }
}

const oAuthService = new OAuthService();
export default oAuthService;
