import NetInfo from '@react-native-community/netinfo';
import * as Haptics from 'expo-haptics';
import { StatusBar as ExpoStatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    ActivityIndicator,
    Animated,
    BackHandler,
    Easing,
    Image,
    KeyboardAvoidingView, // Fix #2
    Platform,
    Pressable,
    StyleSheet,
    Text,
    View,
} from 'react-native';
import { SafeAreaProvider, useSafeAreaInsets } from 'react-native-safe-area-context';
import { WebView } from 'react-native-webview';
import NativeService from './services/NativeService';

// ─── CONFIGURATION ────────────────────────────────────────────────────────────
const APP_CONFIG = {
  baseUrl: process.env.EXPO_PUBLIC_BASE_URL || 'https://api.keyhome.neocraft.dev/owner',
  appMode: 'native',
  primaryColor: '#10b981',   // Vert Owner
  accentColor:  '#059669',
  splashBg:     '#ffffff',
};

// Fix #17 — userAgent précis sans révéler le framework interne
const USER_AGENT = `KeyHome/1.0 (Owner; ${Platform.OS === 'ios' ? 'iOS' : 'Android'})`;

// JS injecté dans la WebView pour exposer le bridge natif
const INJECTED_JS = `
  (function() {
    if (window.__keyHomeNativeBridgeReady) return;
    window.__keyHomeNativeBridgeReady = true;
    window.isNativeApp   = true;
    window.appMode       = 'native';
    window.platform      = '${Platform.OS}';

    // Helper côté web pour appeler les fonctions natives
    window.KeyHomeBridge = {
      pickImage:   (opts) => window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'PICK_IMAGE',        data: opts || {} })),
      takePhoto:   (opts) => window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'TAKE_PHOTO',        data: opts || {} })),
      getLocation: ()     => window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'REQUEST_LOCATION',  data: {} })),
      registerPush:()     => window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'REGISTER_PUSH',     data: {} })),
      signInGoogle:(p)    => window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'OAUTH_SIGN_IN',     data: { provider: 'google', panelType: p || 'bailleur' } })),
      // Fix #11 — Haptics depuis la WebView
      haptic:      (style) => window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'HAPTIC', data: { style: style || 'light' } })),
      // Fix #14 — StatusBar depuis la WebView
      setStatusBar:(style) => window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'SET_STATUS_BAR', data: { style } })),
    };
    true;
  })();
`;

// ─── COMPOSANT INTERNE (accès aux insets) ─────────────────────────────────────
function AppContent() {
  // ── États ──────────────────────────────────────────────────────────────────
  const [showSplash,    setShowSplash]    = useState(true);
  const [isLoading,     setIsLoading]     = useState(true);
  const [isFirstLoad,   setIsFirstLoad]   = useState(true);
  const [error,         setError]         = useState(null);
  const [isOffline,     setIsOffline]     = useState(false);
  const [canGoBack,     setCanGoBack]     = useState(false);
  const [statusBarStyle, setStatusBarStyle] = useState('dark'); // Fix #14

  // ── Refs ───────────────────────────────────────────────────────────────────
  const webViewRef     = useRef(null);
  const splashTimer    = useRef(null);
  const fadeAnim       = useRef(new Animated.Value(1)).current;
  const scaleAnim      = useRef(new Animated.Value(0.3)).current;
  const insets         = useSafeAreaInsets(); // Fix #1

  // ── Bouton back Android ────────────────────────────────────────────────────
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

  // ── Animation splash + surveillance réseau ─────────────────────────────────
  useEffect(() => {
    Animated.spring(scaleAnim, {
      toValue: 1,
      friction: 5,
      tension: 60,
      useNativeDriver: true,
    }).start();

    const unsubscribeNet = NetInfo.addEventListener(state => {
      setIsOffline(!state.isConnected);
    });

    return () => {
      unsubscribeNet();
      clearTimeout(splashTimer.current);
      NativeService.cleanup();
    };
  }, []);

  // ── Refs WebView → NativeService ───────────────────────────────────────────
  const setWebViewRef = useCallback((ref) => {
    webViewRef.current = ref;
    if (ref) NativeService.initialize(webViewRef);
  }, []);

  // ── Disparition splash ─────────────────────────────────────────────────────
  const hideSplash = useCallback(() => {
    Animated.timing(fadeAnim, {
      toValue: 0,
      duration: 400,
      easing: Easing.out(Easing.quad),
      useNativeDriver: true,
    }).start(() => setShowSplash(false));
  }, [fadeAnim]);

  // ── Handlers WebView ───────────────────────────────────────────────────────
  const handleLoadEnd = useCallback(() => {
    if (isFirstLoad) {
      setIsLoading(false);
      setIsFirstLoad(false);
    }
    if (showSplash) {
      clearTimeout(splashTimer.current);
      splashTimer.current = setTimeout(hideSplash, 600);
    }
  }, [isFirstLoad, showSplash, hideSplash]);

  const handleLoadStart = useCallback(() => {
    if (isFirstLoad) setIsLoading(true);
  }, [isFirstLoad]);

  const handleHttpError = useCallback((syntheticEvent) => {
    const { statusCode, url } = syntheticEvent.nativeEvent;
    console.warn(`[WebView] HTTP Error ${statusCode} on ${url}`);

    // Fix #7 — Rediriger vers login si session expirée
    if (statusCode === 401) {
      webViewRef.current?.injectJavaScript(
        `window.location.href = '${APP_CONFIG.baseUrl}/login'; true;`
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

  const handleError = useCallback((syntheticEvent) => {
    const { nativeEvent } = syntheticEvent;
    console.error('[WebView] Network error:', nativeEvent.description);
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

  // Fix #14 — Handler de messages natifs étendu (StatusBar, Haptics)
  const handleMessage = useCallback((event) => {
    try {
      const msg = JSON.parse(event.nativeEvent.data);
      if (msg?.type === 'SET_STATUS_BAR') {
        setStatusBarStyle(msg.data?.style || 'dark');
        return;
      }
    } catch {
      // message non-JSON — relayer au NativeService
    }
    NativeService.handleWebViewMessage(event);
  }, []);

  // ── URL ────────────────────────────────────────────────────────────────────
  const APP_URL = `${APP_CONFIG.baseUrl}?app_mode=${APP_CONFIG.appMode}&platform=${Platform.OS}`;

  // ─────────────────────────────────────────────────────────────────────────
  return (
    <View style={styles.container}>
      {/* Fix #14 — StatusBar adaptive au contenu dark/light */}
      <ExpoStatusBar style={statusBarStyle} backgroundColor={APP_CONFIG.splashBg} translucent={false} />

      {/* Fix #1 — Bannière hors-ligne dans la safe area (paddingTop = inset top) */}
      {isOffline && (
        <View style={[styles.offlineBanner, { marginTop: insets.top }]}>
          <Text style={styles.offlineText}>📶 Hors ligne — données en cache</Text>
        </View>
      )}

      {/* Fix #2 — KeyboardAvoidingView pour que les formulaires Filament restent visibles */}
      <KeyboardAvoidingView
        style={styles.keyboardView}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={insets.top}
      >
        <WebView
          ref={setWebViewRef}
          source={{ uri: APP_URL }}
          style={styles.webview}

          onLoadStart={handleLoadStart}
          onLoadEnd={handleLoadEnd}
          onError={handleError}
          onHttpError={handleHttpError}
          onNavigationStateChange={(s) => setCanGoBack(s.canGoBack)}

          javaScriptEnabled={true}
          domStorageEnabled={true}
          cacheEnabled={true}
          cacheMode="LOAD_DEFAULT"
          allowFileAccess={false}
          allowsBackForwardNavigationGestures={true}
          pullToRefreshEnabled={false}
          scrollEnabled={true}
          bounces={Platform.OS === 'ios'}              // rebond natif iOS
          overScrollMode={Platform.OS === 'android' ? 'always' : undefined}
          keyboardShouldPersistTaps="handled"

          // Fix #4 — dataDetectorTypes iOS uniquement (crash Android New Arch)
          dataDetectorTypes={Platform.OS === 'ios' ? ['phoneNumber', 'link'] : 'none'}

          injectedJavaScriptBeforeContentLoaded={INJECTED_JS}
          onMessage={handleMessage}                   // Fix #14

          originWhitelist={['https://*', 'http://localhost:*']}
          // Fix #17 — userAgent sans révéler le framework
          userAgent={USER_AGENT}

          renderLoading={() => null}
          startInLoadingState={false}
        />
      </KeyboardAvoidingView>

      {/* Fix #1 — Écran d'erreur respectant les insets */}
      {error && !showSplash && (
        <View style={[styles.errorContainer, {
          paddingTop: insets.top,
          paddingBottom: insets.bottom,
        }]}>
          <View style={styles.errorCard}>
            <Text style={styles.errorEmoji}>
              {error.type === 'network' ? '📡' : '⚠️'}
            </Text>
            <Text style={styles.errorTitle}>{error.message}</Text>
            <Text style={styles.errorDetails}>{error.details}</Text>

            <Pressable
              style={({ pressed }) => [
                styles.retryButton,
                pressed && styles.retryButtonPressed,
              ]}
              onPress={handleRetry}
              accessibilityRole="button"
              accessibilityLabel="Réessayer"
            >
              <Text style={styles.retryButtonText}>🔄 Réessayer</Text>
            </Pressable>
          </View>
        </View>
      )}

      {/* Loader navigation (hors-splash) */}
      {isLoading && !showSplash && !error && (
        <View style={styles.loaderContainer}>
          <Image
            source={require('./assets/icon.png')}
            style={styles.loaderLogo}
            tintColor={APP_CONFIG.primaryColor}
          />
          <ActivityIndicator size="large" color={APP_CONFIG.primaryColor} />
        </View>
      )}

      {/* Splash screen */}
      {showSplash && (
        <Animated.View style={[styles.splashContainer, { opacity: fadeAnim }]}>
          <View style={styles.splashContent}>
            <Animated.Image
              source={require('./assets/icon.png')}
              style={[
                styles.splashLogo,
                { transform: [{ scale: scaleAnim }] },
              ]}
              tintColor={APP_CONFIG.primaryColor}
            />
            <Text style={styles.splashTitle}>KeyHome Owner</Text>
            <Text style={styles.splashSubtitle}>Gérez vos biens en toute sérénité</Text>
            <View style={styles.splashLoader}>
              <ActivityIndicator size="small" color={APP_CONFIG.primaryColor} />
            </View>
          </View>
          <Text style={[styles.splashVersion, { bottom: 48 + insets.bottom }]}>
            v1.0.0 Pro Edition
          </Text>
        </Animated.View>
      )}
    </View>
  );
}

// ─── COMPOSANT PRINCIPAL ──────────────────────────────────────────────────────
export default function App() {
  return (
    <SafeAreaProvider>
      <AppContent />
    </SafeAreaProvider>
  );
}

// ─── STYLES ──────────────────────────────────────────────────────────────────
const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  keyboardView: {
    flex: 1,
  },
  webview: {
    flex: 1,
    backgroundColor: '#ffffff',
  },

  offlineBanner: {
    backgroundColor: '#f59e0b',
    paddingVertical: 6,
    paddingHorizontal: 16,
    alignItems: 'center',
    zIndex: 200,
  },
  offlineText: {
    color: '#ffffff',
    fontSize: 13,
    fontWeight: '600',
  },

  loaderContainer: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    zIndex: 10,
  },
  loaderLogo: {
    width: 90,
    height: 90,
    resizeMode: 'contain',
    marginBottom: 24,
  },

  splashContainer: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#ffffff',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 100,
  },
  splashContent: {
    alignItems: 'center',
  },
  splashLogo: {
    width: 140,
    height: 140,
    resizeMode: 'contain',
    marginBottom: 20,
  },
  splashTitle: {
    fontSize: 28,
    fontWeight: '900',
    color: '#1e293b',
    letterSpacing: -1,
  },
  splashSubtitle: {
    fontSize: 14,
    fontWeight: '500',
    color: '#64748b',
    marginTop: 6,
  },
  splashLoader: {
    marginTop: 48,
  },
  splashVersion: {
    position: 'absolute',
    fontSize: 12,
    fontWeight: '700',
    color: 'rgba(148,163,184,0.5)',
  },

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
  errorEmoji: {
    fontSize: 60,
    marginBottom: 16,
  },
  errorTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: '#0f172a',
    textAlign: 'center',
    marginBottom: 8,
  },
  errorDetails: {
    fontSize: 14,
    color: '#64748b',
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: 28,
  },
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
  retryButtonPressed: {
    backgroundColor: '#059669',
    transform: [{ scale: 0.97 }],
  },
  retryButtonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '700',
    letterSpacing: 0.3,
  },
});
