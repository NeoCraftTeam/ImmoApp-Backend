import NetInfo from '@react-native-community/netinfo';
import * as Haptics from 'expo-haptics';
import * as Linking from 'expo-linking';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar as ExpoStatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  BackHandler,
  Easing,
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaProvider, useSafeAreaInsets } from 'react-native-safe-area-context';
import WebView from 'react-native-webview';
import type { WebViewMessageEvent, WebViewNavigation } from 'react-native-webview';

import { APP_CONFIG, USER_AGENT } from './src/config';
import BiometricService from './src/services/BiometricService';
import NativeService from './src/services/NativeService';
import OAuthService from './src/services/OAuthService';
import type { StatusBarStyle } from './src/types';

SplashScreen.preventAutoHideAsync();

/**
 * Build the injected JS with an optional Sanctum token.
 * If a token is available from SecureStore, it's injected as an
 * Authorization header interceptor so the WebView session is
 * pre-authenticated — avoiding the login redirect.
 */
function buildInjectedJs(sanctumToken: string | null): string {
  const tokenInjection = sanctumToken
    ? `
    // Preload Sanctum token — intercept fetch to add Authorization header
    (function() {
      var _origFetch = window.fetch;
      window.fetch = function(input, init) {
        init = init || {};
        init.headers = init.headers || {};
        if (typeof init.headers.set === 'function') {
          if (!init.headers.has('Authorization')) {
            init.headers.set('Authorization', 'Bearer ${sanctumToken}');
          }
        } else {
          if (!init.headers['Authorization']) {
            init.headers['Authorization'] = 'Bearer ${sanctumToken}';
          }
        }
        return _origFetch.call(this, input, init);
      };
    })();
    `
    : '';

  return `
  (function() {
    if (window.__keyHomeNativeBridgeReady) return;
    window.__keyHomeNativeBridgeReady = true;
    window.isNativeApp   = true;
    window.appMode       = 'native';
    window.platform      = '${Platform.OS}';
    ${tokenInjection}
    window.KeyHomeBridge = {
      pickImage:    function(opts) { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'PICK_IMAGE',       data: opts || {} })); },
      takePhoto:    function(opts) { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'TAKE_PHOTO',       data: opts || {} })); },
      getLocation:  function()     { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'REQUEST_LOCATION', data: {} })); },
      registerPush: function()     { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'REGISTER_PUSH',    data: {} })); },
      signInGoogle: function(p)    { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'OAUTH_SIGN_IN',    data: { provider: 'google', panelType: p || 'bailleur' } })); },
      haptic:       function(s)    { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'HAPTIC',           data: { style: s || 'light' } })); },
      setStatusBar: function(s)    { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'SET_STATUS_BAR',   data: { style: s } })); },
    };
    true;
  })();
`;
}

interface AppError {
  type: 'network' | 'server';
  code?: number;
  message: string;
  details: string;
}

function AppContent() {
  const [showSplash, setShowSplash] = useState(true);
  const [isLoading, setIsLoading] = useState(true);
  const [isFirstLoad, setIsFirstLoad] = useState(true);
  const [error, setError] = useState<AppError | null>(null);
  const [isOffline, setIsOffline] = useState(false);
  const [canGoBack, setCanGoBack] = useState(false);
  const [statusBarStyle, setStatusBarStyle] = useState<StatusBarStyle>('dark');
  const [biometricLocked, setBiometricLocked] = useState(false);
  const [biometricLabel, setBiometricLabel] = useState('Biométrie');
  const [injectedJs, setInjectedJs] = useState<string>(buildInjectedJs(null));

  const webViewRef = useRef<WebView>(null);
  const splashTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const fadeAnim = useRef(new Animated.Value(1)).current;
  const scaleAnim = useRef(new Animated.Value(0.3)).current;
  const insets = useSafeAreaInsets();

  useEffect(() => {
    (async () => {
      // Load stored Sanctum token for pre-authentication
      const storedToken = await OAuthService.getAuthToken();
      if (storedToken) {
        setInjectedJs(buildInjectedJs(storedToken));
      }

      const passed = await BiometricService.gate();
      if (!passed) {
        setBiometricLocked(true);
        const label = await BiometricService.getBiometricLabel();
        setBiometricLabel(label);
      }
      await SplashScreen.hideAsync();
    })();
  }, []);

  useEffect(() => {
    if (Platform.OS !== 'android') return;
    const handler = BackHandler.addEventListener('hardwareBackPress', () => {
      if (canGoBack && webViewRef.current) {
        webViewRef.current.goBack();
        return true;
      }
      return false;
    });
    return () => handler.remove();
  }, [canGoBack]);

  useEffect(() => {
    Animated.spring(scaleAnim, {
      toValue: 1,
      friction: 5,
      tension: 60,
      useNativeDriver: true,
    }).start();

    const unsubscribeNet = NetInfo.addEventListener((state) => {
      setIsOffline(!state.isConnected);
    });

    return () => {
      unsubscribeNet();
      if (splashTimer.current) clearTimeout(splashTimer.current);
      NativeService.cleanup();
    };
  }, [scaleAnim]);

  // Deep linking: navigate WebView when app is opened via keyhome-owner:// URL
  useEffect(() => {
    const handleDeepLink = (event: { url: string }) => {
      const parsed = Linking.parse(event.url);
      if (parsed.path) {
        const targetUrl = `${APP_CONFIG.baseUrl}/${parsed.path}`;
        NativeService.navigateWebViewTo(targetUrl);
      }
    };

    const subscription = Linking.addEventListener('url', handleDeepLink);

    Linking.getInitialURL().then((url) => {
      if (url) handleDeepLink({ url });
    });

    return () => subscription.remove();
  }, []);

  const setWebViewRefCallback = useCallback((ref: WebView | null) => {
    (webViewRef as React.MutableRefObject<WebView | null>).current = ref;
    if (ref) NativeService.initialize(webViewRef);
  }, []);

  const hideSplash = useCallback(() => {
    Animated.timing(fadeAnim, {
      toValue: 0,
      duration: 400,
      easing: Easing.out(Easing.quad),
      useNativeDriver: true,
    }).start(() => setShowSplash(false));
  }, [fadeAnim]);

  const handleLoadEnd = useCallback(() => {
    if (isFirstLoad) {
      setIsLoading(false);
      setIsFirstLoad(false);
    }
    if (showSplash) {
      if (splashTimer.current) clearTimeout(splashTimer.current);
      splashTimer.current = setTimeout(hideSplash, 600);
    }

    if (insets.top > 0 && webViewRef.current) {
      const top = insets.top;
      const bottom = insets.bottom;
      webViewRef.current.injectJavaScript(`
        (function() {
          document.documentElement.style.setProperty('--rn-safe-top',    '${top}px');
          document.documentElement.style.setProperty('--rn-safe-bottom', '${bottom}px');
          var topbar = document.querySelector('.fi-topbar');
          if (topbar) topbar.style.paddingTop = '${top}px';
          var sidebarHeader = document.querySelector('.fi-sidebar-header');
          if (sidebarHeader) sidebarHeader.style.paddingTop = '${top + 16}px';
          if (!window.__rnSafeAreaObserver) {
            window.__rnSafeAreaObserver = true;
            document.addEventListener('livewire:navigated', function() {
              var tb = document.querySelector('.fi-topbar');
              if (tb) tb.style.paddingTop = '${top}px';
              var sh = document.querySelector('.fi-sidebar-header');
              if (sh) sh.style.paddingTop = '${top + 16}px';
            });
          }
          true;
        })();
      `);
    }
  }, [isFirstLoad, showSplash, hideSplash, insets.top, insets.bottom]);

  const handleLoadStart = useCallback(() => {
    if (isFirstLoad) setIsLoading(true);
  }, [isFirstLoad]);

  const handleHttpError = useCallback((syntheticEvent: { nativeEvent: { statusCode: number; url: string } }) => {
    const { statusCode } = syntheticEvent.nativeEvent;

    if (statusCode === 401) {
      webViewRef.current?.injectJavaScript(
        `window.location.href = '${APP_CONFIG.baseUrl}/login'; true;`,
      );
      return;
    }

    if (statusCode >= 500) {
      setError({
        type: 'server',
        code: statusCode,
        message: `Erreur serveur (${statusCode})`,
        details: 'Le serveur rencontre un problème. Réessayez dans quelques instants.',
      });
      setIsLoading(false);
    }
  }, []);

  const handleError = useCallback(() => {
    setError({
      type: 'network',
      message: 'Impossible de se connecter',
      details: 'Vérifiez votre connexion internet et réessayez.',
    });
    setIsLoading(false);
  }, []);

  const handleRetry = useCallback(() => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    setError(null);
    setIsFirstLoad(true);
    setIsLoading(true);
    webViewRef.current?.reload();
  }, []);

  const handleMessage = useCallback((event: WebViewMessageEvent) => {
    try {
      const msg = JSON.parse(event.nativeEvent.data) as { type?: string; data?: { style?: string } };
      if (msg?.type === 'SET_STATUS_BAR') {
        setStatusBarStyle((msg.data?.style as StatusBarStyle) ?? 'dark');
        return;
      }
    } catch {
      // Non-JSON message — relay to NativeService
    }
    NativeService.handleWebViewMessage(event);
  }, []);

  const handleNavigationStateChange = useCallback((s: WebViewNavigation) => {
    setCanGoBack(s.canGoBack);
  }, []);

  const handleBiometricRetry = useCallback(async () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    const passed = await BiometricService.authenticate();
    if (passed) setBiometricLocked(false);
  }, []);

  const APP_URL = `${APP_CONFIG.baseUrl}?app_mode=${APP_CONFIG.appMode}&platform=${Platform.OS}`;

  // Biometric lock screen
  if (biometricLocked) {
    return (
      <View style={[styles.biometricContainer, { paddingTop: insets.top, paddingBottom: insets.bottom }]}>
        <ExpoStatusBar style="dark" />
        <View style={styles.biometricContent}>
          <Image source={require('./assets/icon.png')} style={styles.biometricLogo} tintColor={APP_CONFIG.primaryColor} />
          <Text style={styles.biometricTitle}>KeyHome Owner</Text>
          <Text style={styles.biometricSubtitle}>Authentification requise</Text>
          <Pressable
            style={({ pressed }) => [styles.biometricButton, pressed && styles.biometricButtonPressed]}
            onPress={handleBiometricRetry}
            accessibilityRole="button"
            accessibilityLabel={`Déverrouiller avec ${biometricLabel}`}
          >
            <Text style={styles.biometricButtonText}>Déverrouiller avec {biometricLabel}</Text>
          </Pressable>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <ExpoStatusBar style={statusBarStyle} backgroundColor={APP_CONFIG.splashBg} translucent={false} />

      {isOffline && (
        <View style={[styles.offlineBanner, { marginTop: insets.top }]}>
          <Text style={styles.offlineText}>Hors ligne — données en cache</Text>
        </View>
      )}

      <KeyboardAvoidingView
        style={styles.keyboardView}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={insets.top}
      >
        <WebView
          ref={setWebViewRefCallback}
          source={{ uri: APP_URL }}
          style={styles.webview}
          onLoadStart={handleLoadStart}
          onLoadEnd={handleLoadEnd}
          onError={handleError}
          onHttpError={handleHttpError}
          onNavigationStateChange={handleNavigationStateChange}
          onMessage={handleMessage}
          javaScriptEnabled
          domStorageEnabled
          cacheEnabled
          cacheMode="LOAD_DEFAULT"
          allowFileAccess={false}
          allowsBackForwardNavigationGestures
          pullToRefreshEnabled={false}
          scrollEnabled
          bounces={Platform.OS === 'ios'}
          overScrollMode={Platform.OS === 'android' ? 'always' : undefined}
          {...(Platform.OS === 'ios' && { dataDetectorTypes: ['phoneNumber', 'link'] })}
          injectedJavaScriptBeforeContentLoaded={injectedJs}
          originWhitelist={['https://*', 'http://localhost:*']}
          userAgent={USER_AGENT}
          renderLoading={() => <View />}
          startInLoadingState={false}
        />
      </KeyboardAvoidingView>

      {error && !showSplash && (
        <View style={[styles.errorContainer, { paddingTop: insets.top, paddingBottom: insets.bottom }]}>
          <View style={styles.errorCard}>
            <Text style={styles.errorEmoji}>{error.type === 'network' ? '📡' : '⚠️'}</Text>
            <Text style={styles.errorTitle}>{error.message}</Text>
            <Text style={styles.errorDetails}>{error.details}</Text>
            <Pressable
              style={({ pressed }) => [styles.retryButton, pressed && styles.retryButtonPressed]}
              onPress={handleRetry}
              accessibilityRole="button"
              accessibilityLabel="Réessayer"
            >
              <Text style={styles.retryButtonText}>Réessayer</Text>
            </Pressable>
          </View>
        </View>
      )}

      {isLoading && !showSplash && !error && (
        <View style={styles.loaderContainer}>
          <Image source={require('./assets/icon.png')} style={styles.loaderLogo} tintColor={APP_CONFIG.primaryColor} />
          <ActivityIndicator size="large" color={APP_CONFIG.primaryColor} />
        </View>
      )}

      {showSplash && (
        <Animated.View style={[styles.splashContainer, { opacity: fadeAnim }]}>
          <View style={styles.splashContent}>
            <Animated.Image
              source={require('./assets/icon.png')}
              style={[styles.splashLogo, { transform: [{ scale: scaleAnim }] }]}
              tintColor={APP_CONFIG.primaryColor}
            />
            <Text style={styles.splashTitle}>KeyHome Owner</Text>
            <Text style={styles.splashSubtitle}>Gérez vos biens en toute sérénité</Text>
            <View style={styles.splashLoader}>
              <ActivityIndicator size="small" color={APP_CONFIG.primaryColor} />
            </View>
          </View>
          <Text style={[styles.splashVersion, { bottom: 48 + insets.bottom }]}>
            v{APP_CONFIG.version} Pro Edition
          </Text>
        </Animated.View>
      )}
    </View>
  );
}

export default function App() {
  return (
    <SafeAreaProvider>
      <AppContent />
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#ffffff' },
  keyboardView: { flex: 1 },
  webview: { flex: 1, backgroundColor: '#ffffff' },

  offlineBanner: {
    backgroundColor: '#f59e0b',
    paddingVertical: 6,
    paddingHorizontal: 16,
    alignItems: 'center',
    zIndex: 200,
  },
  offlineText: { color: '#ffffff', fontSize: 13, fontWeight: '600' },

  loaderContainer: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    zIndex: 10,
  },
  loaderLogo: { width: 90, height: 90, resizeMode: 'contain', marginBottom: 24 },

  splashContainer: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#ffffff',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 100,
  },
  splashContent: { alignItems: 'center' },
  splashLogo: { width: 140, height: 140, resizeMode: 'contain', marginBottom: 20 },
  splashTitle: { fontSize: 28, fontWeight: '900', color: '#1e293b', letterSpacing: -1 },
  splashSubtitle: { fontSize: 14, fontWeight: '500', color: '#64748b', marginTop: 6 },
  splashLoader: { marginTop: 48 },
  splashVersion: { position: 'absolute', fontSize: 12, fontWeight: '700', color: 'rgba(148,163,184,0.5)' },

  errorContainer: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#0f172a',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 50,
    padding: 24,
  },
  errorCard: {
    backgroundColor: '#ffffff',
    borderRadius: 24,
    padding: 36,
    alignItems: 'center',
    maxWidth: 360,
    width: '100%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.25,
    shadowRadius: 24,
    elevation: 12,
  },
  errorEmoji: { fontSize: 60, marginBottom: 16 },
  errorTitle: { fontSize: 20, fontWeight: '800', color: '#0f172a', textAlign: 'center', marginBottom: 8 },
  errorDetails: { fontSize: 14, color: '#64748b', textAlign: 'center', lineHeight: 20, marginBottom: 28 },
  retryButton: {
    backgroundColor: '#10b981',
    width: '100%',
    paddingVertical: 14,
    borderRadius: 14,
    alignItems: 'center',
    shadowColor: '#10b981',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.35,
    shadowRadius: 10,
    elevation: 6,
  },
  retryButtonPressed: { backgroundColor: '#059669', transform: [{ scale: 0.97 }] },
  retryButtonText: { color: '#ffffff', fontSize: 16, fontWeight: '700', letterSpacing: 0.3 },

  biometricContainer: {
    flex: 1,
    backgroundColor: '#ffffff',
    justifyContent: 'center',
    alignItems: 'center',
  },
  biometricContent: { alignItems: 'center', paddingHorizontal: 32 },
  biometricLogo: { width: 100, height: 100, resizeMode: 'contain', marginBottom: 24 },
  biometricTitle: { fontSize: 28, fontWeight: '900', color: '#1e293b', letterSpacing: -1, marginBottom: 8 },
  biometricSubtitle: { fontSize: 15, fontWeight: '500', color: '#64748b', marginBottom: 40 },
  biometricButton: {
    backgroundColor: '#10b981',
    paddingVertical: 16,
    paddingHorizontal: 32,
    borderRadius: 16,
    width: '100%',
    alignItems: 'center',
    shadowColor: '#10b981',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.35,
    shadowRadius: 10,
    elevation: 6,
  },
  biometricButtonPressed: { backgroundColor: '#059669', transform: [{ scale: 0.97 }] },
  biometricButtonText: { color: '#ffffff', fontSize: 16, fontWeight: '700' },
});
