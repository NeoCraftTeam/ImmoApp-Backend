import { StatusBar as ExpoStatusBar } from 'expo-status-bar';
import React, { useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  Dimensions,
  Image,
  StyleSheet,
  Text,
  View
} from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import { WebView } from 'react-native-webview';
import NativeService from './services/NativeService';

const { width, height } = Dimensions.get('window');

// CONFIGURATION
const APP_CONFIG = {
  baseUrl: 'http://192.168.1.64:8000/agency', // Local IP pour test mobile en local
  appMode: 'native',
  primaryColor: '#ff4757', // Nouvelle couleur KeyHome
  splashDuration: 2500,
};

export default function App() {
  const [isLoading, setIsLoading] = useState(true);
  const [showSplash, setShowSplash] = useState(true);
  const [error, setError] = useState(null);
  const fadeAnim = useRef(new Animated.Value(1)).current;
  const scaleAnim = useRef(new Animated.Value(0.3)).current;
  const pulseAnim = useRef(new Animated.Value(1)).current;
  const webViewRef = useRef(null);

  // Animation du logo au démarrage
  React.useEffect(() => {
    // Initialiser NativeService avec référence vide pour commencer
    // Sera mise à jour quand ref sera disponible
    
    // Animation de scale (apparition)
    Animated.spring(scaleAnim, {
      toValue: 1,
      friction: 4,
      tension: 40,
      useNativeDriver: true,
    }).start();

    // Animation de pulse (battement)
    Animated.loop(
      Animated.sequence([
        Animated.timing(pulseAnim, {
          toValue: 1.05,
          duration: 1000,
          useNativeDriver: true,
        }),
        Animated.timing(pulseAnim, {
          toValue: 1,
          duration: 1000,
          useNativeDriver: true,
        }),
      ])
    ).start();

    return () => {
      NativeService.cleanup();
    };
  }, []);

  // Mettre à jour la référence WebView pour NativeService quand elle change
  const setWebViewRef = (ref) => {
    webViewRef.current = ref;
    if (ref) {
      NativeService.initialize(webViewRef);
    }
  };

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
    <SafeAreaProvider>
      <View style={styles.container}>
        <ExpoStatusBar style="dark" backgroundColor="#ffffff" translucent={false} />
        
        <SafeAreaView style={{flex: 1}}>
          <WebView 
            ref={setWebViewRef}
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
          // Gérer d'abord les messages natifs
          NativeService.handleWebViewMessage(event);
          
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
      </SafeAreaView>

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

      {/* Overlay de chargement (Classique) */}
      {isLoading && !showSplash && !error && (
        <View style={styles.loaderContainer}>
           <Image 
             source={require('./assets/icon.png')} 
             style={{ width: 100, height: 100, resizeMode: 'contain', marginBottom: 30 }} 
           />
           <ActivityIndicator size="large" color={APP_CONFIG.primaryColor} />
        </View>
      )}

      {/* Splash Screen Custom */}
      {showSplash && (
        <Animated.View style={[styles.splashContainer, { opacity: fadeAnim }]}>
          <View style={styles.splashContent}>
             <Animated.Image
               source={require('./assets/icon.png')}
               style={[
                 styles.splashLogoImage,
                 {
                   transform: [
                     { scale: Animated.multiply(scaleAnim, pulseAnim) }
                   ]
                 }
               ]}
             />
             <Text style={styles.splashTitle}>KeyHome Agency</Text>
             <Text style={styles.splashSubtitle}>Gestion Immobilière Intelligente</Text>
             
             <View style={styles.splashLoader}>
                <ActivityIndicator size="small" color={APP_CONFIG.primaryColor} />
             </View>
          </View>
          <Text style={styles.versionText}>v1.0.0 Pro Edition</Text>
        </Animated.View>
      )}
      </View>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  webview: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  
  // Styles du Loader Simple
  loaderContainer: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    zIndex: 10,
  },
  
  // Styles du Splash Screen
  splashContainer: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#ffffff', // Fond blanc
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 100,
  },
  splashContent: {
    alignItems: 'center',
  },
  splashLogoImage: {
      width: 150,
      height: 150,
      resizeMode: 'contain',
      marginBottom: 20,
  },
  splashTitle: {
    color: '#1e293b', // Texte foncé
    fontSize: 28,
    fontWeight: '900',
    letterSpacing: -1,
  },
  splashSubtitle: {
    color: '#64748b', // Gris moyen
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
