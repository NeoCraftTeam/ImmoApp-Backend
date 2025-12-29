# 📱 Guide de Raffinement - Applications Mobiles KeyHome

## Vue d'ensemble

Ce document détaille toutes les améliorations apportées aux applications mobiles **KeyHome Agency** et **KeyHome Bailleur** pour offrir une expérience utilisateur premium et native.

---

## ✅ Phase 1 : UX de Base (TERMINÉE)

### 1.1 Écran d'Erreur Réseau Élégant

**Objectif** : Gérer gracieusement les erreurs de connexion et serveur

**Implémentation** :
- Détection automatique des erreurs réseau (code -1200, timeout, etc.)
- Détection des erreurs serveur (HTTP 500+)
- Affichage d'un écran d'erreur élégant avec icône contextuelle
- Bouton "Réessayer" avec reload de la WebView

**Fichiers modifiés** :
- `mobile/agency/App.js`
- `mobile/bailleur/App.js`

**Code clé** :
```javascript
const [error, setError] = useState(null);

// Dans onError de WebView
setError({
  type: 'network',
  message: 'Impossible de se connecter au serveur',
  details: 'Vérifiez votre connexion internet'
});

// Bouton retry
const handleRetry = () => {
  setError(null);
  setIsLoading(true);
  webViewRef.current.reload();
};
```

**Types d'erreurs gérées** :
- ❌ **Erreur réseau** : Pas de connexion internet, timeout
- ⚠️ **Erreur serveur** : HTTP 500, 502, 503, 504

**Design** :
- Fond sombre (#0f172a pour Agency, #064e3b pour Bailleur)
- Carte blanche glassmorphism
- Icône emoji contextuelle (📡 ou ⚠️)
- Bouton bleu/émeraude avec ombre

---

### 1.2 Skeleton Screen (Loader Amélioré)

**Objectif** : Remplacer le loader simple par un skeleton screen moderne

**Implémentation** :
- Affichage d'un squelette de carte pendant le chargement
- Animation subtile avec fond translucide
- Spinner en dessous pour indiquer l'activité

**Avantages** :
- ✅ Meilleure perception de la vitesse
- ✅ Moins de frustration utilisateur
- ✅ Design moderne et professionnel

**Code clé** :
```javascript
<View style={styles.skeletonCard}>
  <View style={[styles.skeletonLine, styles.skeletonTitle]} />
  <View style={[styles.skeletonLine, styles.skeletonSubtitle]} />
  <View style={styles.skeletonRow}>
    <View style={[styles.skeletonLine, styles.skeletonButton]} />
    <View style={[styles.skeletonLine, styles.skeletonButton]} />
  </View>
</View>
```

**Design** :
- Fond sombre semi-transparent (95% opacité)
- Lignes blanches translucides (20% opacité)
- Bordures arrondies (20px)
- Responsive (90% largeur, max 400px)

---

### 1.3 Gestion du Bouton Retour Android

**Statut** : ⏳ À implémenter

**Objectif** : Gérer le bouton retour natif Android pour navigation dans WebView

**Plan d'implémentation** :
```javascript
import { BackHandler } from 'react-native';

useEffect(() => {
  const backAction = () => {
    if (webViewRef.current) {
      webViewRef.current.goBack();
      return true; // Empêche la fermeture de l'app
    }
    return false;
  };

  const backHandler = BackHandler.addEventListener(
    'hardwareBackPress',
    backAction
  );

  return () => backHandler.remove();
}, []);
```

---

## 🎨 Phase 2 : Branding (À FAIRE)

### 2.1 Icône d'Application Personnalisée

**Objectif** : Remplacer l'icône par défaut Expo par le logo KeyHome

**Étapes** :
1. Créer un logo 1024x1024px (PNG avec fond transparent)
2. Placer dans `mobile/agency/assets/icon.png`
3. Placer dans `mobile/bailleur/assets/icon.png`
4. Mettre à jour `app.json` :
```json
{
  "expo": {
    "icon": "./assets/icon.png",
    "android": {
      "adaptiveIcon": {
        "foregroundImage": "./assets/adaptive-icon.png",
        "backgroundColor": "#3b82f6"  // Agency
        // ou "#10b981" pour Bailleur
      }
    }
  }
}
```

**Outils recommandés** :
- [Icon Kitchen](https://icon.kitchen/) - Générateur d'icônes adaptatifs
- [App Icon Generator](https://appicon.co/) - Génère toutes les tailles

---

### 2.2 Splash Screen Animé

**Objectif** : Améliorer le splash screen avec une animation de logo

**Plan d'implémentation** :
```javascript
// Ajouter une animation de scale au logo
const scaleAnim = useRef(new Animated.Value(0.3)).current;

useEffect(() => {
  Animated.spring(scaleAnim, {
    toValue: 1,
    friction: 3,
    tension: 40,
    useNativeDriver: true,
  }).start();
}, []);

// Dans le render
<Animated.View style={{ transform: [{ scale: scaleAnim }] }}>
  <Text style={styles.logoText}>KH</Text>
</Animated.View>
```

**Améliorations possibles** :
- Animation de rotation subtile
- Effet de "pulse" sur le logo
- Transition fluide vers le contenu

---

## 🚀 Phase 3 : Fonctionnalités Natives (À FAIRE)

### 3.1 Upload de Photos via Caméra

**Objectif** : Permettre l'upload de photos d'annonces depuis l'app

**Dépendances** :
```bash
npx expo install expo-image-picker expo-file-system
```

**Implémentation** :
```javascript
import * as ImagePicker from 'expo-image-picker';

const pickImage = async () => {
  const result = await ImagePicker.launchImageLibraryAsync({
    mediaTypes: ImagePicker.MediaTypeOptions.Images,
    allowsEditing: true,
    aspect: [4, 3],
    quality: 0.8,
  });

  if (!result.canceled) {
    uploadImage(result.assets[0].uri);
  }
};

const uploadImage = async (uri) => {
  const formData = new FormData();
  formData.append('photo', {
    uri,
    type: 'image/jpeg',
    name: 'photo.jpg',
  });

  // Envoyer via bridge à la WebView
  webViewRef.current.postMessage(JSON.stringify({
    type: 'IMAGE_UPLOAD',
    data: formData
  }));
};
```

**Communication WebView ↔ React Native** :
```javascript
// Dans la WebView (JavaScript injecté)
window.addEventListener('message', (event) => {
  const { type, data } = JSON.parse(event.data);
  if (type === 'IMAGE_UPLOAD') {
    // Traiter l'upload côté Laravel
  }
});
```

---

### 3.2 Notifications Push (Firebase)

**Objectif** : Envoyer des notifications pour événements importants

**Configuration Firebase** :
1. Créer un projet Firebase
2. Ajouter les apps iOS et Android
3. Télécharger `google-services.json` (Android) et `GoogleService-Info.plist` (iOS)

**Installation** :
```bash
npx expo install expo-notifications expo-device expo-constants
```

**Implémentation** :
```javascript
import * as Notifications from 'expo-notifications';

// Configuration
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
  }),
});

// Demander permission
const { status } = await Notifications.requestPermissionsAsync();

// Obtenir le token
const token = (await Notifications.getExpoPushTokenAsync()).data;

// Envoyer le token au backend Laravel
await fetch(`${API_URL}/api/v1/users/push-token`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ token }),
});
```

**Backend Laravel** :
```php
// app/Http/Controllers/Api/V1/UserController.php
public function storePushToken(Request $request)
{
    $request->validate(['token' => 'required|string']);
    
    auth()->user()->update([
        'push_token' => $request->token
    ]);
    
    return response()->json(['success' => true]);
}

// Envoyer une notification
use Illuminate\Support\Facades\Http;

Http::post('https://exp.host/--/api/v2/push/send', [
    'to' => $user->push_token,
    'title' => 'Nouvelle annonce',
    'body' => 'Une nouvelle propriété correspond à vos critères',
    'data' => ['adId' => $ad->id],
]);
```

---

### 3.3 Géolocalisation & Cartes

**Objectif** : Afficher les annonces sur une carte interactive

**Dépendances** :
```bash
npx expo install react-native-maps expo-location
```

**Implémentation** :
```javascript
import MapView, { Marker } from 'react-native-maps';
import * as Location from 'expo-location';

// Demander permission
const { status } = await Location.requestForegroundPermissionsAsync();

// Obtenir position
const location = await Location.getCurrentPositionAsync({});

// Afficher carte
<MapView
  style={{ flex: 1 }}
  initialRegion={{
    latitude: location.coords.latitude,
    longitude: location.coords.longitude,
    latitudeDelta: 0.0922,
    longitudeDelta: 0.0421,
  }}
>
  {ads.map(ad => (
    <Marker
      key={ad.id}
      coordinate={{
        latitude: ad.latitude,
        longitude: ad.longitude,
      }}
      title={ad.title}
      description={ad.price}
    />
  ))}
</MapView>
```

---

## 🔒 Phase 4 : Sécurité (À FAIRE)

### 4.1 Authentification Biométrique

**Objectif** : Touch ID / Face ID pour reconnecter rapidement

**Installation** :
```bash
npx expo install expo-local-authentication
```

**Implémentation** :
```javascript
import * as LocalAuthentication from 'expo-local-authentication';

const authenticateWithBiometrics = async () => {
  const hasHardware = await LocalAuthentication.hasHardwareAsync();
  const isEnrolled = await LocalAuthentication.isEnrolledAsync();

  if (hasHardware && isEnrolled) {
    const result = await LocalAuthentication.authenticateAsync({
      promptMessage: 'Connectez-vous avec votre empreinte',
      fallbackLabel: 'Utiliser le mot de passe',
    });

    if (result.success) {
      // Auto-login
      loginWithStoredCredentials();
    }
  }
};
```

**Stockage sécurisé des credentials** :
```bash
npx expo install expo-secure-store
```

```javascript
import * as SecureStore from 'expo-secure-store';

// Sauvegarder
await SecureStore.setItemAsync('userToken', token);

// Récupérer
const token = await SecureStore.getItemAsync('userToken');
```

---

### 4.2 Deep Linking

**Objectif** : Ouvrir l'app depuis un lien (email, SMS, web)

**Configuration dans `app.json`** :
```json
{
  "expo": {
    "scheme": "keyhome",
    "android": {
      "intentFilters": [
        {
          "action": "VIEW",
          "data": [
            {
              "scheme": "https",
              "host": "keyhome.neocraft.dev",
              "pathPrefix": "/ads"
            }
          ],
          "category": ["BROWSABLE", "DEFAULT"]
        }
      ]
    }
  }
}
```

**Gestion dans l'app** :
```javascript
import * as Linking from 'expo-linking';

useEffect(() => {
  const handleDeepLink = (event) => {
    const { path, queryParams } = Linking.parse(event.url);
    
    if (path === 'ads' && queryParams.id) {
      // Naviguer vers l'annonce
      webViewRef.current.injectJavaScript(`
        window.location.href = '/ads/${queryParams.id}';
      `);
    }
  };

  Linking.addEventListener('url', handleDeepLink);
  
  return () => Linking.removeAllListeners('url');
}, []);
```

**Exemple d'utilisation** :
- Lien : `https://keyhome.neocraft.dev/ads/123`
- Ou : `keyhome://ads/123`
- → Ouvre l'app et affiche l'annonce #123

---

## 📊 Performance & Optimisation

### Cache Intelligent

**Objectif** : Réduire les temps de chargement avec cache local

```javascript
import AsyncStorage from '@react-native-async-storage/async-storage';

// Sauvegarder données
await AsyncStorage.setItem('ads_cache', JSON.stringify(ads));

// Récupérer au démarrage
const cachedAds = await AsyncStorage.getItem('ads_cache');
if (cachedAds) {
  setAds(JSON.parse(cachedAds));
}
```

### Mode Hors-ligne

**Objectif** : Permettre la consultation des données même sans connexion

```javascript
import NetInfo from '@react-native-community/netinfo';

const [isOnline, setIsOnline] = useState(true);

useEffect(() => {
  const unsubscribe = NetInfo.addEventListener(state => {
    setIsOnline(state.isConnected);
  });

  return () => unsubscribe();
}, []);

// Afficher un bandeau si hors-ligne
{!isOnline && (
  <View style={styles.offlineBanner}>
    <Text>Mode hors-ligne - Données en cache</Text>
  </View>
)}
```

---

## 🎨 Thème & Design

### Dark Mode

**Objectif** : Support du mode sombre

```javascript
import { useColorScheme } from 'react-native';

const colorScheme = useColorScheme();
const isDark = colorScheme === 'dark';

const styles = StyleSheet.create({
  container: {
    backgroundColor: isDark ? '#0f172a' : '#ffffff',
  },
  text: {
    color: isDark ? '#ffffff' : '#0f172a',
  },
});
```

---

## 📦 Build & Déploiement

### Build de Production

**Pour Android (APK)** :
```bash
cd mobile/agency
eas build --platform android --profile production
```

**Pour iOS (IPA)** :
```bash
cd mobile/agency
eas build --platform ios --profile production
```

### Configuration EAS Build

Créer `eas.json` :
```json
{
  "build": {
    "production": {
      "android": {
        "buildType": "apk"
      },
      "ios": {
        "buildConfiguration": "Release"
      }
    },
    "development": {
      "developmentClient": true,
      "distribution": "internal"
    }
  }
}
```

### Publication sur les Stores

**Google Play Store** :
1. Créer un compte développeur (25$ one-time)
2. Préparer les assets (icône, screenshots, description)
3. Upload l'APK/AAB
4. Soumettre pour review

**Apple App Store** :
1. Créer un compte développeur (99$/an)
2. Configurer App Store Connect
3. Upload l'IPA via Xcode ou Transporter
4. Soumettre pour review

---

## 🧪 Tests & Qualité

### Tests Unitaires

```bash
npm install --save-dev jest @testing-library/react-native
```

```javascript
// App.test.js
import { render } from '@testing-library/react-native';
import App from './App';

test('renders splash screen', () => {
  const { getByText } = render(<App />);
  expect(getByText('KeyHome Agency')).toBeTruthy();
});
```

### Tests E2E

```bash
npm install --save-dev detox
```

---

## 📝 Checklist de Raffinement

### Phase 1 : UX de Base
- [x] Écran d'erreur réseau élégant
- [x] Skeleton screen (loader amélioré)
- [ ] Gestion bouton retour Android

### Phase 2 : Branding
- [ ] Icône d'app personnalisée
- [ ] Splash screen animé
- [ ] Screenshots pour stores

### Phase 3 : Fonctionnalités Natives
- [ ] Upload photos via caméra
- [ ] Notifications push (Firebase)
- [ ] Géolocalisation & cartes

### Phase 4 : Sécurité
- [ ] Authentification biométrique
- [ ] Deep linking
- [ ] Stockage sécurisé

### Phase 5 : Performance
- [ ] Cache intelligent
- [ ] Mode hors-ligne
- [ ] Optimisation images

### Phase 6 : Design
- [ ] Dark mode
- [ ] Thème cohérent
- [ ] Animations fluides

### Phase 7 : Déploiement
- [ ] Build Android (APK/AAB)
- [ ] Build iOS (IPA)
- [ ] Publication Google Play
- [ ] Publication App Store

---

## 🆘 Troubleshooting

### Erreur "Metro bundler not running"
```bash
cd mobile/agency
npx expo start --clear
```

### Erreur de build iOS
```bash
cd ios
pod install
cd ..
npx expo run:ios
```

### Erreur de permissions Android
Ajouter dans `app.json` :
```json
{
  "expo": {
    "android": {
      "permissions": [
        "CAMERA",
        "READ_EXTERNAL_STORAGE",
        "WRITE_EXTERNAL_STORAGE",
        "ACCESS_FINE_LOCATION"
      ]
    }
  }
}
```

---

## 📚 Ressources

- [Expo Documentation](https://docs.expo.dev/)
- [React Native Documentation](https://reactnative.dev/)
- [Filament Documentation](https://filamentphp.com/)
- [Firebase Documentation](https://firebase.google.com/docs)

---

**Dernière mise à jour** : 29 décembre 2025
**Version** : 1.0.0
**Auteur** : Équipe KeyHome
