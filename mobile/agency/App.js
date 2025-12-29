import { StatusBar as ExpoStatusBar } from 'expo-status-bar';
import { useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  Dimensions,
  SafeAreaView,
  StyleSheet,
  Text,
  View
} from 'react-native';
import { WebView } from 'react-native-webview';

const { width, height } = Dimensions.get('window');

// CONFIGURATION
const APP_CONFIG = {
  baseUrl: 'http://192.168.1.64:8000/agency', // Local IP pour test mobile en local
  appMode: 'native',
  primaryColor: '#3b82f6',
  splashDuration: 2500,
};

export default function App() {
  const [isLoading, setIsLoading] = useState(true);
  const [showSplash, setShowSplash] = useState(true);
  const [error, setError] = useState(null);
  const fadeAnim = useRef(new Animated.Value(1)).current;
  const webViewRef = useRef(null);

  // Masquer le splash screen avec une animation
  const hideSplash = () => {
    Animated.timing(fadeAnim, {
      toValue: 0,
      duration: 500,
      useNativeDriver: true,
    }).start(() => setShowSplash(false));
  };

  // URL enrichie avec le mode natif
  const getAppUrl = () => {
    const url = `${APP_CONFIG.baseUrl}?app_mode=${APP_CONFIG.appMode}`;
    console.log('Loading URL:', url);
    return url;
  };

  const APP_URL = 'http://192.168.1.64:8000/agency?app_mode=native';

  // Retry loading
  const handleRetry = () => {
    setError(null);
    setIsLoading(true);
    if (webViewRef.current) {
      webViewRef.current.reload();
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <ExpoStatusBar style="light" backgroundColor="#0f172a" />
      
      {/* WebView principale */}
      <WebView 
        ref={webViewRef}
        source={{ uri: APP_URL }}
        style={styles.webview}
        onLoadStart={() => setIsLoading(true)}
        onLoadEnd={() => {
          setIsLoading(false);
          if (showSplash) setTimeout(hideSplash, 1000);
        }}
        javaScriptEnabled={true}
        sharedCookiesEnabled={true}
        thirdPartyCookiesEnabled={true}
        domStorageEnabled={true}
        cacheEnabled={true}
        incognito={false}
        mixedContentMode="always"
        userAgent="KeyHomeAgencyMobileApp/1.0"
        onMessage={(event) => {
          if (event.nativeEvent.data === 'AUTH_SUCCESS') {
             console.log("Login successful detected!");
          }
          console.log("WebView Console:", event.nativeEvent.data);
        }}
        injectedJavaScript={`
          (function() {
            var oldLog = console.log;
            console.log = function (message) {
              window.ReactNativeWebView.postMessage(message);
              oldLog.apply(console, arguments);
            };
          })();
        `}
        onShouldStartLoadWithRequest={(request) => {
          // Allow all requests including HTTP
          return true;
        }}
        onError={(syntheticEvent) => {
          const { nativeEvent } = syntheticEvent;
          console.error('WebView error:', nativeEvent);
          setError({
            type: 'network',
            message: 'Impossible de se connecter au serveur',
            details: nativeEvent.description || 'Vérifiez votre connexion internet'
          });
          setIsLoading(false);
        }}
        onHttpError={(syntheticEvent) => {
          const { nativeEvent } = syntheticEvent;
          console.warn('WebView received error status code: ', nativeEvent.statusCode);
          if (nativeEvent.statusCode >= 500) {
            setError({
              type: 'server',
              message: 'Le serveur rencontre des difficultés',
              details: 'Veuillez réessayer dans quelques instants'
            });
            setIsLoading(false);
          }
        }}
      />

      {/* Error Screen */}
      {error && !showSplash && (
        <View style={styles.errorContainer}>
          <View style={styles.errorContent}>
            <Text style={styles.errorIcon}>{error.type === 'network' ? '📡' : '⚠️'}</Text>
            <Text style={styles.errorTitle}>{error.message}</Text>
            <Text style={styles.errorDetails}>{error.details}</Text>
            <View style={styles.buttonRow}>
              <View style={styles.retryButton}>
                <Text style={styles.retryButtonText} onPress={handleRetry}>
                  🔄 Réessayer
                </Text>
              </View>
            </View>
          </View>
        </View>
      )}

      {/* Overlay de chargement (Skeleton Screen) */}
      {isLoading && !showSplash && !error && (
        <View style={styles.loaderContainer}>
          <View style={styles.skeletonCard}>
            <View style={[styles.skeletonLine, styles.skeletonTitle]} />
            <View style={[styles.skeletonLine, styles.skeletonSubtitle]} />
            <View style={styles.skeletonRow}>
              <View style={[styles.skeletonLine, styles.skeletonButton]} />
              <View style={[styles.skeletonLine, styles.skeletonButton]} />
            </View>
          </View>
          <ActivityIndicator size="large" color={APP_CONFIG.primaryColor} style={{ marginTop: 20 }} />
        </View>
      )}

      {/* Splash Screen Custom */}
      {showSplash && (
        <Animated.View style={[styles.splashContainer, { opacity: fadeAnim }]}>
          <View style={styles.splashContent}>
             <View style={styles.logoCircle}>
                <Text style={styles.logoText}>KH</Text>
             </View>
             <Text style={styles.splashTitle}>KeyHome Agency</Text>
             <Text style={styles.splashSubtitle}>Gestion Immobilière Intelligente</Text>
             
             <View style={styles.splashLoader}>
                <ActivityIndicator size="small" color="#ffffff" />
             </View>
          </View>
          <Text style={styles.versionText}>v1.0.0 Pro Edition</Text>
        </Animated.View>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#0f172a',
  },
  webview: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  
  // Styles du Loader & Skeleton
  loaderContainer: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(15, 23, 42, 0.95)',
    zIndex: 10,
    padding: 20,
  },
  skeletonCard: {
    backgroundColor: 'rgba(255, 255, 255, 0.1)',
    borderRadius: 20,
    padding: 24,
    width: '90%',
    maxWidth: 400,
  },
  skeletonLine: {
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    borderRadius: 8,
    marginBottom: 12,
  },
  skeletonTitle: {
    height: 32,
    width: '70%',
  },
  skeletonSubtitle: {
    height: 20,
    width: '50%',
    marginBottom: 24,
  },
  skeletonRow: {
    flexDirection: 'row',
    gap: 12,
  },
  skeletonButton: {
    height: 48,
    flex: 1,
  },

  // Styles du Splash Screen
  splashContainer: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#0f172a', // Fond sombre premium
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 100,
  },
  splashContent: {
    alignItems: 'center',
  },
  logoCircle: {
      width: 100,
      height: 100,
      borderRadius: 50,
      backgroundColor: '#3b82f6',
      justifyContent: 'center',
      alignItems: 'center',
      marginBottom: 20,
      shadowColor: '#3b82f6',
      shadowOffset: { width: 0, height: 10 },
      shadowOpacity: 0.5,
      shadowRadius: 20,
      elevation: 20,
  },
  logoText: {
      color: '#ffffff',
      fontSize: 40,
      fontWeight: '900',
  },
  splashTitle: {
    color: '#ffffff',
    fontSize: 28,
    fontWeight: '900',
    letterSpacing: -1,
  },
  splashSubtitle: {
    color: '#94a3b8',
    fontSize: 14,
    fontWeight: '500',
    marginTop: 5,
  },
  splashLoader: {
    marginTop: 50,
  },
  versionText: {
    position: 'absolute',
    bottom: 50,
    color: 'rgba(148, 163, 184, 0.5)',
    fontSize: 12,
    fontWeight: '700',
  },

  // Error Screen Styles
  errorContainer: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#0f172a',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 50,
    padding: 20,
  },
  errorContent: {
    backgroundColor: 'rgba(255, 255, 255, 0.95)',
    borderRadius: 24,
    padding: 40,
    alignItems: 'center',
    maxWidth: 350,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.2,
    shadowRadius: 20,
    elevation: 10,
  },
  errorIcon: {
    fontSize: 64,
    marginBottom: 20,
  },
  errorTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: '#0f172a',
    textAlign: 'center',
    marginBottom: 10,
  },
  errorDetails: {
    fontSize: 14,
    color: '#64748b',
    textAlign: 'center',
    marginBottom: 30,
    lineHeight: 20,
  },
  buttonRow: {
    width: '100%',
  },
  retryButton: {
    backgroundColor: '#3b82f6',
    paddingVertical: 14,
    paddingHorizontal: 32,
    borderRadius: 12,
    alignItems: 'center',
    shadowColor: '#3b82f6',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 5,
  },
  retryButtonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '700',
  },
});
