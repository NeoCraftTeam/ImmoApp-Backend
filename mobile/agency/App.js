import { StatusBar as ExpoStatusBar } from 'expo-status-bar';
import React, { useRef, useState } from 'react';
import {
    ActivityIndicator,
    Animated,
    BackHandler,
    Image,
    Platform,
    StyleSheet,
    Text,
    View
} from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import { WebView } from 'react-native-webview';
import NativeService from './services/NativeService';

// CONFIGURATION
const APP_CONFIG = {
  baseUrl: process.env.EXPO_PUBLIC_BASE_URL || 'https://api.keyhome.neocraft.dev/agency', 
  appMode: 'native',
  primaryColor: '#2563eb', // Bleu Agence
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
  const [canGoBack, setCanGoBack] = useState(false);

  // Android back button → navigate back in WebView
  React.useEffect(() => {
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

  React.useEffect(() => {
    Animated.spring(scaleAnim, {
      toValue: 1,
      friction: 4,
      tension: 40,
      useNativeDriver: true,
    }).start();

    const pulseLoop = Animated.loop(
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
    );
    pulseLoop.start();

    return () => {
      pulseLoop.stop();
      NativeService.cleanup();
    };
  }, []);

  const setWebViewRef = (ref) => {
    webViewRef.current = ref;
    if (ref) {
      NativeService.initialize(webViewRef);
    }
  };

  const hideSplash = () => {
    Animated.timing(fadeAnim, {
      toValue: 0,
      duration: 500,
      useNativeDriver: true,
    }).start(() => setShowSplash(false));
  };

  const getAppUrl = () => {
    return `${APP_CONFIG.baseUrl}?app_mode=${APP_CONFIG.appMode}`;
  };

  const APP_URL = getAppUrl();

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
        
        <SafeAreaView style={{flex: 1, backgroundColor: '#ffffff'}}>
            <WebView 
	            ref={setWebViewRef}
	            source={{ uri: APP_URL }}
	            style={styles.webview}
	            onLoadStart={() => setIsLoading(true)}
	            onLoadEnd={() => {
	              setIsLoading(false);
	              if (showSplash) setTimeout(hideSplash, APP_CONFIG.splashDuration);
	            }}
	            onNavigationStateChange={(navState) => setCanGoBack(navState.canGoBack)}
	            javaScriptEnabled={true}
	            domStorageEnabled={true}
	            cacheEnabled={true}
	            cacheMode="LOAD_DEFAULT"
	            userAgent="KeyHomeAgencyMobileApp/1.0"
	            onMessage={(event) => {
	              // Sécurité : Valider l'origine du message si possible
	              NativeService.handleWebViewMessage(event);
	            }}
	            // Autoriser HTTP (dev local) et HTTPS (production)
	            originWhitelist={['https://*']}
	            // Sécurité : Désactiver l'accès au système de fichiers
	            allowFileAccess={false}
	            // Sécurité : Empêcher l'exécution de JS injecté de manière non sécurisée
	            injectedJavaScriptBeforeContentLoaded={`
	              (function() {
	                window.isNativeApp = true;
	              })();
	            `}
	            onError={(syntheticEvent) => {
              const { nativeEvent } = syntheticEvent;
              setError({
                type: 'network',
                message: 'Impossible de se connecter au serveur',
                details: 'Vérifiez votre connexion internet'
              });
              setIsLoading(false);
            }}
          />
        </SafeAreaView>

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

        {isLoading && !showSplash && !error && (
          <View style={styles.loaderContainer}>
             <Image 
               source={require('./assets/icon.png')} 
               style={{ width: 100, height: 100, resizeMode: 'contain', marginBottom: 30, tintColor: APP_CONFIG.primaryColor }} 
             />
             <ActivityIndicator size="large" color={APP_CONFIG.primaryColor} />
          </View>
        )}

        {showSplash && (
          <Animated.View style={[styles.splashContainer, { opacity: fadeAnim }]}>
            <View style={styles.splashContent}>
               <Animated.Image
                 source={require('./assets/icon.png')}
                 style={[
                   styles.splashLogoImage,
                   {
                     tintColor: APP_CONFIG.primaryColor,
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
  loaderContainer: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    zIndex: 10,
    padding: 20,
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
  splashLogoImage: {
      width: 150,
      height: 150,
      resizeMode: 'contain',
      marginBottom: 20,
  },
  splashTitle: {
    color: '#1e293b',
    fontSize: 28,
    fontWeight: '900',
    letterSpacing: -1,
  },
  splashSubtitle: {
    color: '#64748b',
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
    backgroundColor: '#2563eb', // Bleu Theme
    paddingVertical: 14,
    paddingHorizontal: 32,
    borderRadius: 12,
    alignItems: 'center',
    shadowColor: '#2563eb',
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
