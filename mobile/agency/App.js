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
    Platform,
    Pressable,
    StyleSheet,
    Text,
    View,
} from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import { WebView } from 'react-native-webview';
import NativeService from './services/NativeService';

// ─── CONFIGURATION ────────────────────────────────────────────────────────────
const APP_CONFIG = {
  baseUrl: process.env.EXPO_PUBLIC_BASE_URL || 'https://api.keyhome.neocraft.dev/agency',
  appMode: 'native',
  primaryColor: '#2563eb',   // Bleu Agence
  accentColor:  '#1d4ed8',
  splashBg:     '#ffffff',
};

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
      signInGoogle:(p)    => window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'OAUTH_SIGN_IN',     data: { provider: 'google', panelType: p || 'agency' } })),
    };
    true;
  })();
`;

// ─── COMPOSANT PRINCIPAL ──────────────────────────────────────────────────────
export default function App() {
  // ── États ──────────────────────────────────────────────────────────────────
  const [showSplash,   setShowSplash]   = useState(true);
  const [isLoading,    setIsLoading]    = useState(true);
  const [isFirstLoad,  setIsFirstLoad]  = useState(true);   // FIX #3 : loader nav
  const [error,        setError]        = useState(null);
  const [isOffline,    setIsOffline]    = useState(false);
  const [canGoBack,    setCanGoBack]    = useState(false);

  // ── Refs ───────────────────────────────────────────────────────────────────
  const webViewRef     = useRef(null);
  const splashTimer    = useRef(null);                       // FIX #2 : memory leak
  const fadeAnim       = useRef(new Animated.Value(1)).current;
  const scaleAnim      = useRef(new Animated.Value(0.3)).current;
  const loadingOpacity = useRef(new Animated.Value(1)).current;

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
    // Animation d'entrée du splash
    Animated.spring(scaleAnim, {
      toValue: 1,
      friction: 5,
      tension: 60,
      useNativeDriver: true,
    }).start();

    // Surveillance réseau (NetInfo)
    const unsubscribeNet = NetInfo.addEventListener(state => {
      setIsOffline(!state.isConnected);
    });

    return () => {
      unsubscribeNet();
      clearTimeout(splashTimer.current);   // FIX #2 : cleanup timer
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
    // FIX #3 : loader uniquement au premier chargement
    if (isFirstLoad) {
      setIsLoading(false);
      setIsFirstLoad(false);
    }
    // FIX #1 : splash disparaît dès le load sans délai superflu
    if (showSplash) {
      clearTimeout(splashTimer.current);
      splashTimer.current = setTimeout(hideSplash, 600); // petit délai visuel seulement
    }
  }, [isFirstLoad, showSplash, hideSplash]);

  const handleLoadStart = useCallback(() => {
    // FIX #3 : ne pas reshaper le loader après le premier chargement
    if (isFirstLoad) setIsLoading(true);
  }, [isFirstLoad]);

  // FIX #7 : capturer les erreurs HTTP (4xx / 5xx)
  const handleHttpError = useCallback((syntheticEvent) => {
    const { statusCode, url } = syntheticEvent.nativeEvent;
    console.warn(`[WebView] HTTP Error ${statusCode} on ${url}`);
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

  // FIX #4 : retry avec Haptics
  const handleRetry = useCallback(() => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    setError(null);
    setIsFirstLoad(true);
    setIsLoading(true);
    webViewRef.current?.reload();
  }, []);

  // ── URL ────────────────────────────────────────────────────────────────────
  const APP_URL = `${APP_CONFIG.baseUrl}?app_mode=${APP_CONFIG.appMode}&platform=${Platform.OS}`;

  // ─────────────────────────────────────────────────────────────────────────
  return (
    <SafeAreaProvider>
      <View style={styles.container}>
        <ExpoStatusBar style="dark" backgroundColor={APP_CONFIG.splashBg} translucent={false} />

        {/* ── Bannière hors-ligne ── */}
        {isOffline && (
          <View style={styles.offlineBanner}>
            <Text style={styles.offlineText}>📶 Hors ligne — données en cache</Text>
          </View>
        )}

        <SafeAreaView style={styles.safeArea}>
          <WebView
            ref={setWebViewRef}
            source={{ uri: APP_URL }}
            style={styles.webview}

            // ── Événements de chargement ──────────────────────────────────
            onLoadStart={handleLoadStart}
            onLoadEnd={handleLoadEnd}
            onError={handleError}
            onHttpError={handleHttpError}               // FIX #7
            onNavigationStateChange={(s) => setCanGoBack(s.canGoBack)}

            // ── Fonctionnalités ───────────────────────────────────────────
            javaScriptEnabled={true}
            domStorageEnabled={true}
            cacheEnabled={true}
            cacheMode="LOAD_DEFAULT"
            allowFileAccess={false}
            allowsBackForwardNavigationGestures={true}  // iOS swipe
            pullToRefreshEnabled={false}

            // ── Bridge natif ──────────────────────────────────────────────
            injectedJavaScriptBeforeContentLoaded={INJECTED_JS}
            onMessage={(event) => NativeService.handleWebViewMessage(event)}

            // ── Sécurité ──────────────────────────────────────────────────
            originWhitelist={['https://*', 'http://localhost:*']}
            userAgent="KeyHomeAgencyMobileApp/1.0 (Expo; React-Native)"

            // ── Perf ──────────────────────────────────────────────────────
            renderLoading={() => null}           // on gère nous-mêmes
            startInLoadingState={false}
          />
        </SafeAreaView>

        {/* ── Écran d'erreur ─────────────────────────────────────────────── */}
        {error && !showSplash && (
          <View style={styles.errorContainer}>
            <View style={styles.errorCard}>
              <Text style={styles.errorEmoji}>
                {error.type === 'network' ? '📡' : '⚠️'}
              </Text>
              <Text style={styles.errorTitle}>{error.message}</Text>
              <Text style={styles.errorDetails}>{error.details}</Text>

              {/* FIX #4 : Pressable au lieu de Text onPress */}
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

        {/* ── Loader navigation (hors-splash) ───────────────────────────── */}
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

        {/* ── Splash screen ─────────────────────────────────────────────── */}
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
              <Text style={styles.splashTitle}>KeyHome Agency</Text>
              <Text style={styles.splashSubtitle}>Gestion Immobilière Intelligente</Text>
              <View style={styles.splashLoader}>
                <ActivityIndicator size="small" color={APP_CONFIG.primaryColor} />
              </View>
            </View>
            <Text style={styles.splashVersion}>v1.0.0 Pro Edition</Text>
          </Animated.View>
        )}
      </View>
    </SafeAreaProvider>
  );
}

// ─── STYLES ──────────────────────────────────────────────────────────────────
const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  safeArea: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  webview: {
    flex: 1,
    backgroundColor: '#ffffff',
  },

  // ── Bannière offline ────────────────────────────────────────────────────
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

  // ── Loader ──────────────────────────────────────────────────────────────
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

  // ── Splash ───────────────────────────────────────────────────────────────
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
    bottom: 48,
    fontSize: 12,
    fontWeight: '700',
    color: 'rgba(148,163,184,0.5)',
  },

  // ── Erreur ───────────────────────────────────────────────────────────────
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
    backgroundColor: '#2563eb',
    width: '100%',
    paddingVertical: 14,
    borderRadius: 14,
    alignItems: 'center',
    shadowColor: '#2563eb',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.35,
    shadowRadius: 10,
    elevation: 6,
  },
  retryButtonPressed: {
    backgroundColor: '#1d4ed8',
    transform: [{ scale: 0.97 }],
  },
  retryButtonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '700',
    letterSpacing: 0.3,
  },
});
