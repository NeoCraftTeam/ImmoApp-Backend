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
  baseUrl: 'http://192.168.1.64:8000/bailleur', // Local IP pour test mobile en local
  appMode: 'native',
  primaryColor: '#10b981', // Émeraude pour les Bailleurs
  splashDuration: 2500,
};

export default function App() {
  const [isLoading, setIsLoading] = useState(true);
  const [showSplash, setShowSplash] = useState(true);
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
    return `${APP_CONFIG.baseUrl}?app_mode=${APP_CONFIG.appMode}`;
  };

  return (
    <SafeAreaView style={styles.container}>
      <ExpoStatusBar style="light" backgroundColor="#064e3b" />
      
      {/* WebView principale */}
      <WebView 
        ref={webViewRef}
        source={{ uri: getAppUrl() }}
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
        userAgent="KeyHomeBailleurMobileApp/1.0"
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
        }}
        onHttpError={(syntheticEvent) => {
          const { nativeEvent } = syntheticEvent;
          console.warn('WebView received error status code: ', nativeEvent.statusCode);
        }}
      />

      {/* Overlay de chargement (Loader) */}
      {isLoading && !showSplash && (
        <View style={styles.loaderContainer}>
          <View style={styles.loaderGlass}>
            <ActivityIndicator size="large" color={APP_CONFIG.primaryColor} />
            <Text style={styles.loaderText}>Chargement...</Text>
          </View>
        </View>
      )}

      {/* Splash Screen Custom */}
      {showSplash && (
        <Animated.View style={[styles.splashContainer, { opacity: fadeAnim }]}>
          <View style={styles.splashContent}>
             <View style={styles.logoCircle}>
                <Text style={styles.logoText}>KB</Text>
             </View>
             <Text style={styles.splashTitle}>KeyHome Bailleur</Text>
             <Text style={styles.splashSubtitle}>Suivi de Patrimoine & Loyers</Text>
             
             <View style={styles.splashLoader}>
                <ActivityIndicator size="small" color="#ffffff" />
             </View>
          </View>
          <Text style={styles.versionText}>v1.0.0 Investor Edition</Text>
        </Animated.View>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#064e3b', // Fond vert forêt sombre
  },
  webview: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  
  // Styles du Loader
  loaderContainer: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(6, 78, 59, 0.4)',
    zIndex: 10,
  },
  loaderGlass: {
    padding: 30,
    backgroundColor: 'rgba(255, 255, 255, 0.95)',
    borderRadius: 24,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.1,
    shadowRadius: 20,
    elevation: 5,
  },
  loaderText: {
    marginTop: 15,
    color: '#064e3b',
    fontWeight: '700',
    fontSize: 14,
  },

  // Styles du Splash Screen
  splashContainer: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#064e3b',
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
      backgroundColor: '#10b981',
      justifyContent: 'center',
      alignItems: 'center',
      marginBottom: 20,
      shadowColor: '#10b981',
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
    color: '#a7f3d0',
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
    color: 'rgba(167, 243, 208, 0.4)',
    fontSize: 12,
    fontWeight: '700',
  }
});
