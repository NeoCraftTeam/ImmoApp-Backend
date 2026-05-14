# Audit — `mobile/bailleur` + Filament Bailleur Panel

> Objectifs : compatibilité React Native WebView + UI/UX niveau GAFAM

---

## 🔴 Priorité HAUTE — Compatibilité React Native

### 1. `SafeAreaView` mal positionné — bords coupés sur iPhone
**Fichier :** [App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/App.js#L183)

Le `SafeAreaView` englobe uniquement la WebView (L183), mais les overlays (offlineBanner, splash, errorContainer) sont **en dehors** de la safe area → ils peuvent se retrouver sous la Dynamic Island / barre de navigation Android.

```diff
- <SafeAreaView style={styles.safeArea}>
-   <WebView ... />
- </SafeAreaView>
+ {/* Tous les overlays doivent respecter la safe area */}
+ <SafeAreaView style={styles.safeArea} edges={['top','bottom']}>
+   <WebView ... />
+   {isOffline && <OfflineBanner />}
+   {error && <ErrorCard />}
+ </SafeAreaView>
```

### 2. `keyboardAvoidingView` absent — formulaires Filament cachés par le clavier
**Fichier :** [App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/App.js#L184)

Quand un champ Filament (search, textinput) est focus, le clavier remonte et cache les champs. Manque un `KeyboardAvoidingView` wrappant la WebView.

```js
import { KeyboardAvoidingView } from 'react-native';

<KeyboardAvoidingView
  style={{ flex: 1 }}
  behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
>
  <WebView ... />
</KeyboardAvoidingView>
```

### 3. Scroll Filament — `pull-to-refresh` désactivé mais pas de scroll natif
**Fichier :** [App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/App.js#L201)

`pullToRefreshEnabled={false}` (L201) mais aucun gestionnaire de scroll natif. Les listes Filament avec SoftScrolling ne rebondissent pas correctement sur iOS. Ajouter :

```js
scrollEnabled={true}
bounces={true}          // iOS — effets de rebond natifs
overScrollMode="always" // Android
```

### 4. `dataDetectorTypes` manquant — numéros de téléphone non-cliquables
Les colonnes Filament qui affichent des numéros de téléphone ne sont pas cliquables. Sur iOS uniquement :

```js
dataDetectorTypes={Platform.OS === 'ios' ? ['phoneNumber', 'link'] : 'none'}
```

### 5. `NativeService` — messages silencieux non synchronisés
**Fichier :** [NativeService.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/services/NativeService.js#L107)

Les messages `IMAGE_SELECTED` et `LOCATION_RECEIVED` sont envoyés via `postMessage` sans confirmation de réception côté WebView. Si Livewire est en cours de chargement, le message est perdu.

**Fix :** implémenter un système request/response avec timeout + retry :
```js
async sendWithRetry(type, data, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    this.sendToWebView(type, data);
    await new Promise(r => setTimeout(r, 300));
    if (this._ackReceived) return;
  }
}
```

### 6. `mobile-bridge.blade.php` — origin `null` accepté en prod
**Fichier :** [mobile-bridge.blade.php](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/resources/views/filament/mobile-bridge.blade.php#L4)

```js
const ALLOWED_ORIGINS = ['null', '{{ config("app.url") }}'];
```

`'null'` est l'origin envoyée par la WebView RN — **mais aussi par toute iframe sandboxée**. Filtrer plutôt sur `window.isNativeApp` injecté par le bridge JS :

```js
if (!window.isNativeApp && !ALLOWED_ORIGINS.includes(event.origin)) return;
```

### 7. `handleHttpError` ne gère pas le 401 — pas de redirect vers login
**Fichier :** [App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/App.js#L133)

Un token expiré retourne 401 → la WebView affiche une page blanche. Ajouter :

```js
if (statusCode === 401) {
  webViewRef.current?.injectJavaScript(`
    window.location.href = '${APP_CONFIG.baseUrl}/login';
  `);
}
```

---

## 🟡 Priorité MOYENNE — UI/UX GAFAM Level

### 8. Splash Screen — logo statique, pas de lottie
**Fichier :** [App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/App.js#L252)

Le splash actuel utilise une `Animated.Image` basique. Les apps GAFAM utilisent des animations Lottie (JSON légères). Exemple Apple, Airbnb, Uber — toutes ont des splashes animés.

**Recommandation :** `lottie-react-native` avec une animation SVG/Lottie du logo KeyHome.

### 9. Offline Banner — trop minimaliste
**Fichier :** [App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/App.js#L177)

Le banner actuel est un simple fond amber avec emoji. Niveau GAFAM :
- Icône SVG animée (signal wifi barré)
- Snackbar en bas (pattern Google Material 3) plutôt qu'en haut
- Auto-dismiss + message de reconnexion

### 10. Error Screen — pas de deep link vers support
**Fichier :** [App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/App.js#L214)

L'écran d'erreur a uniquement "Réessayer". Niveau GAFAM :
```js
// Bouton secondaire "Contacter le support"
<Pressable onPress={() => Linking.openURL('mailto:support@keyhome.app')}>
  <Text>Contacter le support</Text>
</Pressable>
```

### 11. Haptics — uniquement sur retry, manque dans les interactions
**Fichier :** [App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/App.js#L160)

Haptics seulement sur `handleRetry`. Niveau Apple/Google :
- `ImpactFeedbackStyle.Light` sur chaque ouverture de modal Filament
- `NotificationFeedbackType.Success` après upload image réussi
- `NotificationFeedbackType.Error` après erreur serveur

**Côté bridge :** envoyer un message `HAPTIC` depuis la WebView → NativeService :
```js
case 'HAPTIC':
  Haptics.impactAsync(data.style || Haptics.ImpactFeedbackStyle.Light);
  break;
```

### 12. Fonts — pas de font custom (utilise la police système)
L'app utilise `fontSize` sans font family — dépend de la police système (San Francisco sur iOS, Roboto sur Android). Les apps GAFAM utilisent leur propre typo.

**Recommandation :** `expo-font` + Inter (Google Fonts) ou SF Pro-like.

### 13. Deep Linking — pas configuré
`app.json` n'a pas de `scheme` ni d'`intentFilters`. Les notifications push ne peuvent pas ouvrir une page spécifique.

```json
"scheme": "keyhome-owner",
"intentFilters": [{ "action": "VIEW", "data": [{ "scheme": "keyhome-owner" }], "category": ["BROWSABLE", "DEFAULT"] }]
```

### 14. `StatusBar` — pas adaptative au contenu dark/light
**Fichier :** [App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/App.js#L174)

`style="dark"` hardcodé. Le panel Filament supporte le dark mode — la StatusBar devrait basculer en `light` quand le panel est en mode sombre.

**Fix :** écouter `prefers-color-scheme` depuis la WebView et envoyer un message `SET_STATUS_BAR` au natif.

---

## 🟢 Priorité BASSE — Optimisations Filament Panel

### 15. Filament Bailleur — 3 resources seulement (Ads, Payments, Reviews)
Le panel bailleur est minimal. Pour l'UX GAFAM : dashboard avec indicateurs clés (loyers du mois, taux d'occupation, avis récents) via widgets Filament.

### 16. `mobile-bridge.blade.php` — localisation GPS uniquement
Le bridge ne gère que `LOCATION_RECEIVED`. Les messages `IMAGE_SELECTED` et `PHOTO_TAKEN` ne sont pas interceptés côté web → les file-uploader Filament ne reçoivent pas les images natives.

**Recommandation :** implémenter un handler `IMAGE_SELECTED` côté web qui injecte le fichier dans le Filament `FileUpload` component.

### 17. Sécurité — `userAgent` révèle trop d'infos
**Fichier :** [App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/App.js#L207)

```js
userAgent="KeyHomeOwnerMobileApp/1.0 (Expo; React-Native)"
```

Révèle le framework. Mieux :
```js
userAgent="KeyHome/1.0 (Owner; iOS)" // ou Android
```

---

## Résumé des priorités

| # | Problème | Impact | Effort |
|---|----------|--------|--------|
| 1 | SafeArea overlays | 🔴 Crash visuel iPhone | Faible |
| 2 | KeyboardAvoidingView | 🔴 Formulaires inaccessibles | Faible |
| 7 | 401 redirect login | 🔴 Session expirée → blanc | Faible |
| 16 | Image bridge WebView | 🔴 Upload natif cassé | Moyen |
| 5 | Message retry/ack | 🟡 Reliability | Moyen |
| 6 | Origin null en prod | 🟡 Sécurité | Faible |
| 11 | Haptics GAFAM | 🟡 UX polish | Moyen |
| 13 | Deep Linking | 🟡 Notifs push utiles | Moyen |
| 14 | StatusBar adaptive | 🟡 Dark mode | Faible |
| 8 | Lottie splash | 🟢 Premium UX | Élevé |
| 12 | Fonts custom | 🟢 Branding | Moyen |
| 15 | Dashboard widgets | 🟢 UX app owner | Élevé |
