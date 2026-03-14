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
    // Force white background on login/simple pages (bypass CSS cache)
    var _mobileCSS = document.createElement('style');
    _mobileCSS.id = '__kh_native_css_early';
    _mobileCSS.textContent = '.fi-simple-page{background:#fff!important}.fi-simple-page::before,.fi-simple-page::after{display:none!important}.fi-simple-main,.fi-simple-page .fi-simple-main{max-width:100%!important;width:100%!important;border-radius:0!important;box-shadow:none!important;border:none!important;background:transparent!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;padding:1.5rem 1.5rem!important}.fi-simple-main::before{display:none!important}.fi-simple-main-ctn{max-width:100%!important;padding:0!important}::-webkit-scrollbar{display:none}body{-ms-overflow-style:none;scrollbar-width:none}html{-webkit-overflow-scrolling:touch}';
    (document.head || document.documentElement).appendChild(_mobileCSS);

    window.KeyHomeBridge = {
      pickImage:    function(opts) { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'PICK_IMAGE',       data: opts || {} })); },
      takePhoto:    function(opts) { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'TAKE_PHOTO',       data: opts || {} })); },
      getLocation:  function()     { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'REQUEST_LOCATION', data: {} })); },
      registerPush: function()     { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'REGISTER_PUSH',    data: {} })); },
      signInGoogle: function(p)    { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'OAUTH_SIGN_IN',    data: { provider: 'google', panelType: p || 'bailleur' } })); },
      haptic:       function(s)    { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'HAPTIC',           data: { style: s || 'light' } })); },
      setStatusBar: function(s)    { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'SET_STATUS_BAR',   data: { style: s } })); },
      getBiometricStatus: function() { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'GET_BIOMETRIC_STATUS', data: {} })); },
      setBiometric: function(on)   { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'SET_BIOMETRIC',    data: { enabled: !!on } })); },
      logout:       function()     { window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'LOGOUT',            data: {} })); },
    };

    // Intercept Filament logout to clear native token
    document.addEventListener('submit', function(e) {
      var form = e.target;
      if (form && form.action && form.action.indexOf('logout') !== -1) {
        window.KeyHomeBridge.logout();
      }
    }, true);

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

    if (webViewRef.current) {
      const top = insets.top;
      const bottom = insets.bottom;
      webViewRef.current.injectJavaScript(`
        (function() {
          document.documentElement.style.setProperty('--rn-safe-top',    '${top}px');
          document.documentElement.style.setProperty('--rn-safe-bottom', '${bottom}px');

          if (!document.getElementById('__kh_native_css')) {
            var s = document.createElement('style');
            s.id = '__kh_native_css';
            s.textContent = [
              ':root { --kh-safe-top: ${top}px; --kh-safe-bottom: ${bottom}px; }',

              /* Login / register — full-screen white */
              '.fi-simple-page { background: #ffffff !important; }',
              '.fi-simple-page::before, .fi-simple-page::after { display: none !important; }',
              '.fi-simple-main, .fi-simple-page .fi-simple-main {',
              '  max-width: 100% !important; width: 100% !important;',
              '  border-radius: 0 !important; box-shadow: none !important;',
              '  border: none !important; background: transparent !important;',
              '  backdrop-filter: none !important; -webkit-backdrop-filter: none !important;',
              '  padding: 1.5rem 1.5rem !important;',
              '}',
              '.fi-simple-main::before { display: none !important; }',
              '.fi-simple-main-ctn { max-width: 100% !important; padding: 0 !important; }',
              '.fi-simple-page .fi-btn-primary { width: 100% !important; border-radius: 14px !important; padding: 0.75rem 1rem !important; }',
              '.fi-simple-page .filament-socialite-buttons button,',
              '.fi-simple-page .filament-socialite-buttons a {',
              '  width: 100% !important; border-radius: 14px !important;',
              '  padding: 0.7rem 1rem !important; justify-content: center !important;',
              '}',
              '.fi-simple-page .fi-input-wrp { border-radius: 14px !important; background: #F9FAFB !important; border: 1.5px solid rgba(0,0,0,0.12) !important; }',
              '.fi-simple-page .fi-input-wrp:focus-within { border-color: #0d9488 !important; background: #fff !important; }',
              '.fi-simple-page .fi-input-wrp input, .fi-simple-page .fi-input-wrp select { font-size: 1rem !important; padding: 0.75rem 1rem !important; }',

              /* Dashboard — native feel */
              '.fi-topbar { padding-top: ${top}px !important; background: #ffffff !important; border-bottom: 1px solid rgba(0,0,0,0.06) !important; position: sticky !important; top: 0 !important; z-index: 100 !important; }',
              '.fi-sidebar-header { padding-top: ${top + 16}px !important; }',
              '.fi-body { background-color: #f8f9fa !important; }',

              /* Sidebar / drawer — must stay above everything */
              '.fi-sidebar-close-overlay { z-index: 200 !important; }',
              '.fi-sidebar { z-index: 300 !important; }',
              'aside.fi-sidebar { z-index: 300 !important; }',

              /* Bottom nav — thick bar covering bottom safe area */
              '.fi-bottom-nav, [class*="bottom-navigation"], nav.fixed.bottom-0, .fi-main-ctn > nav:last-child, .fi-layout > nav {',
              '  padding-bottom: calc(${bottom}px + 8px) !important;',
              '  padding-top: 8px !important;',
              '  z-index: 90 !important;',
              '  background: #ffffff !important;',
              '  border-top: 1px solid rgba(0,0,0,0.08) !important;',
              '  box-shadow: 0 -2px 10px rgba(0,0,0,0.04) !important;',
              '}',
              '.fi-bottom-nav a, .fi-bottom-nav button, nav.fixed.bottom-0 a, nav.fixed.bottom-0 button {',
              '  padding: 4px 0 !important;',
              '  min-height: 44px !important;',
              '  display: flex !important;',
              '  flex-direction: column !important;',
              '  align-items: center !important;',
              '  justify-content: center !important;',
              '}',

              /* Cards — cleaner on mobile */
              '.fi-section { border-radius: 16px !important; overflow: hidden; }',
              '.fi-wi-stats-overview-stat { border-radius: 14px !important; }',
              '.fi-wi-chart { border-radius: 16px !important; }',

              /* Main content — must not overlap topbar or drawer */
              '.fi-main-ctn { position: relative; z-index: 1; }',

              /* Smoother scrolling */
              'html { -webkit-overflow-scrolling: touch; scroll-behavior: smooth; }',

              /* Hide scrollbars for native feel */
              '::-webkit-scrollbar { display: none; }',
              'body { -ms-overflow-style: none; scrollbar-width: none; }',
            ].join('\\n');
            document.head.appendChild(s);
          }

          function applyNativeSafeAreas() {
            var topbar = document.querySelector('.fi-topbar');
            if (topbar) topbar.style.paddingTop = '${top}px';
            var sidebarHeader = document.querySelector('.fi-sidebar-header');
            if (sidebarHeader) sidebarHeader.style.paddingTop = '${top + 16}px';
          }
          applyNativeSafeAreas();

          if (!window.__rnSafeAreaObserver) {
            window.__rnSafeAreaObserver = true;
            document.addEventListener('livewire:navigated', function() {
              applyNativeSafeAreas();
              injectBiometricSettings();
            });
          }

          function injectBiometricSettings() {
            if (!window.isNativeApp) return;
            if (document.getElementById('__kh_biometric_section')) return;
            var profileForm = document.querySelector('.fi-page-edit-profile-page form, [class*="profile"] form');
            var menuPage = document.querySelector('.fi-page');
            var target = profileForm || menuPage;
            if (!target) return;
            var logoutBtn = document.querySelector('form[action*="logout"] button, a[href*="logout"]');
            if (!logoutBtn) return;

            var section = document.createElement('div');
            section.id = '__kh_biometric_section';
            section.style.cssText = 'margin: 1rem 1rem 0; padding: 1rem; background: white; border-radius: 16px; border: 1px solid rgba(0,0,0,0.06);';
            section.innerHTML = '<div style="display:flex;align-items:center;justify-content:space-between">'
              + '<div><div style="font-weight:600;font-size:0.875rem;color:#1e293b">Verrouillage biométrique</div>'
              + '<div id="__kh_bio_label" style="font-size:0.75rem;color:#64748b;margin-top:2px">Chargement...</div></div>'
              + '<label style="position:relative;display:inline-block;width:48px;height:28px">'
              + '<input type="checkbox" id="__kh_bio_toggle" style="opacity:0;width:0;height:0" />'
              + '<span id="__kh_bio_slider" style="position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:28px;transition:.3s"></span>'
              + '<span id="__kh_bio_dot" style="position:absolute;height:22px;width:22px;left:3px;bottom:3px;background:white;border-radius:50%;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,0.2)"></span>'
              + '</label></div>';

            var parent = logoutBtn.closest('.fi-page') || logoutBtn.parentElement?.parentElement;
            if (parent) parent.insertBefore(section, logoutBtn.closest('form') || logoutBtn);
            else document.body.appendChild(section);

            window.KeyHomeBridge.getBiometricStatus();

            var toggle = document.getElementById('__kh_bio_toggle');
            toggle.addEventListener('change', function() {
              window.KeyHomeBridge.setBiometric(toggle.checked);
            });
          }

          window.addEventListener('message', function(e) {
            try {
              var msg = JSON.parse(e.data);
              if (msg.type === 'BIOMETRIC_STATUS' || msg.type === 'BIOMETRIC_SET_RESULT') {
                var d = msg.data || msg;
                var label = document.getElementById('__kh_bio_label');
                var toggle = document.getElementById('__kh_bio_toggle');
                var slider = document.getElementById('__kh_bio_slider');
                var dot = document.getElementById('__kh_bio_dot');
                if (label && d.label) label.textContent = d.available ? d.label : 'Non disponible sur cet appareil';
                if (toggle) { toggle.checked = !!d.enabled; toggle.disabled = !d.available; }
                if (slider) slider.style.background = d.enabled ? '#10b981' : '#cbd5e1';
                if (dot) dot.style.transform = d.enabled ? 'translateX(20px)' : 'translateX(0)';
              }
            } catch(ex) {}
          });

          injectBiometricSettings();
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
    if (s.url && (s.url.includes('/logout') || s.url.endsWith('/login'))) {
      OAuthService.clearAuthToken();
    }
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
          <Image source={require('./assets/splash-icon.png')} style={styles.biometricLogo} />
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
      <ExpoStatusBar style={statusBarStyle} translucent />

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
          sharedCookiesEnabled
          thirdPartyCookiesEnabled
          allowFileAccess={false}
          allowsBackForwardNavigationGestures
          pullToRefreshEnabled
          scrollEnabled
          bounces={Platform.OS === 'ios'}
          hideKeyboardAccessoryView
          allowsInlineMediaPlayback
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
          <Image source={require('./assets/splash-icon.png')} style={styles.loaderLogo} />
          <ActivityIndicator size="large" color={APP_CONFIG.primaryColor} />
        </View>
      )}

      {showSplash && (
        <Animated.View style={[styles.splashContainer, { opacity: fadeAnim }]}>
          <View style={styles.splashContent}>
            <Animated.Image
              source={require('./assets/splash-icon.png')}
              style={[styles.splashLogo, { transform: [{ scale: scaleAnim }] }]}
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
