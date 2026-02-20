import Constants from 'expo-constants';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import * as Notifications from 'expo-notifications';
import OAuthService from './OAuthService';

// ─── Configuration des notifications ─────────────────────────────────────────
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge:  true,
  }),
});

// Origines autorisées (WebView → Native)
const ALLOWED_ORIGINS = [
  'https://keyhomeback.neocraft.dev',
  'https://api.keyhome.neocraft.dev',
  'https://agency.keyhome.neocraft.dev',
  'https://owner.keyhome.neocraft.dev',
];

/**
 * NativeService — Bridge WebView ↔ fonctionnalités natives
 *
 * Messages entrants (depuis la WebView via window.KeyHomeBridge) :
 *   PICK_IMAGE        → ouvre la galerie, retourne IMAGE_SELECTED
 *   TAKE_PHOTO        → ouvre la caméra,  retourne PHOTO_TAKEN
 *   REQUEST_LOCATION  → GPS,              retourne LOCATION_RECEIVED
 *   REGISTER_PUSH     → push token,       retourne PUSH_TOKEN_RECEIVED
 *   OAUTH_SIGN_IN     → Google OAuth,     retourne OAUTH_SUCCESS / OAUTH_ERROR
 *
 * Messages sortants (depuis le natif vers la WebView) :
 *   IMAGE_SELECTED, IMAGE_PERMISSION_DENIED, IMAGE_ERROR
 *   PHOTO_TAKEN,    CAMERA_PERMISSION_DENIED, CAMERA_ERROR
 *   LOCATION_RECEIVED, LOCATION_PERMISSION_DENIED, LOCATION_ERROR
 *   PUSH_TOKEN_RECEIVED, PUSH_PERMISSION_DENIED, PUSH_ERROR
 *   NOTIFICATION_RECEIVED, NOTIFICATION_CLICKED
 *   OAUTH_STARTED, OAUTH_SUCCESS, OAUTH_CANCELLED, OAUTH_ERROR
 */
class NativeService {
  constructor() {
    this.webViewRef           = null;
    this.notificationListener = null;
    this.responseListener     = null;
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Lifecycle
  // ──────────────────────────────────────────────────────────────────────────

  /** Appeler dès que la WebView est montée */
  initialize(webViewRef) {
    this.webViewRef = webViewRef;
    OAuthService.initialize(webViewRef);
    if (!this.notificationListener) {
      this._setupNotificationListeners();
    }
  }

  /** Appeler dans le cleanup du useEffect principal */
  cleanup() {
    this.notificationListener?.remove();
    this.responseListener?.remove();
    this.notificationListener = null;
    this.responseListener     = null;
    OAuthService.cleanup();
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Bridge helpers
  // ──────────────────────────────────────────────────────────────────────────

  /** Envoyer un message JSON à la WebView */
  sendToWebView(type, data = {}) {
    if (this.webViewRef?.current) {
      this.webViewRef.current.postMessage(JSON.stringify({ type, data }));
    }
  }

  /**
   * Point d'entrée unique : tous les messages provenant de la WebView
   * passent ici (handler de onMessage dans la WebView).
   */
  async handleWebViewMessage(event) {
    // 1. Parser le JSON — ignorer les messages non-JSON (ex : logs React)
    let content;
    try {
      content = JSON.parse(event.nativeEvent.data);
    } catch {
      return;
    }

    const { type, data } = content;

    // 2. Valider l'origine (FIX sécurité)
    const origin = event.nativeEvent.url || '';
    const isAllowed = ALLOWED_ORIGINS.some(o => origin.startsWith(o));
    if (!isAllowed) {
      console.warn('[NativeService] Message bloqué depuis origine non autorisée:', origin);
      return;
    }

    // 3. Dispatcher
    try {
      switch (type) {
        case 'PICK_IMAGE':        await this.pickImage(data);                        break;
        case 'TAKE_PHOTO':        await this.takePhoto(data);                        break;
        case 'REQUEST_LOCATION':  await this.getLocation();                          break;
        case 'REGISTER_PUSH':     await this.registerForPushNotifications();         break;
        case 'OAUTH_SIGN_IN':     await OAuthService.handleOAuthRequest(data);       break;
        default:
          console.log('[NativeService] Type inconnu:', type);
      }
    } catch (err) {
      console.error('[NativeService] Erreur non gérée:', err);
    }
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Caméra / Galerie  (FIX #5 : plus de base64 dans le bridge)
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * Sélectionner une image depuis la galerie.
   * Retourne l'URI locale à la WebView ; le web upload directement via fetch.
   */
  async pickImage(options = {}) {
    try {
      const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (status !== 'granted') {
        return this.sendToWebView('IMAGE_PERMISSION_DENIED', {
          message: 'Permission refusée pour accéder aux photos',
        });
      }

      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        allowsEditing: options.allowsEditing !== false,
        aspect:        options.aspect || [4, 3],
        quality:       options.quality || 0.8,
        base64: false,  // FIX #5 : pas de base64 — on passe l'URI uniquement
      });

      if (!result.canceled) {
        const asset = result.assets[0];
        this.sendToWebView('IMAGE_SELECTED', {
          uri:      asset.uri,
          width:    asset.width,
          height:   asset.height,
          mimeType: asset.mimeType || 'image/jpeg',
          fileName: asset.fileName || `photo_${Date.now()}.jpg`,
        });
      }
    } catch (err) {
      this.sendToWebView('IMAGE_ERROR', { error: err.message });
    }
  }

  /**
   * Prendre une photo avec la caméra.
   * FIX #9 : utilise ImagePicker.requestCameraPermissionsAsync (pas expo-camera).
   */
  async takePhoto(options = {}) {
    try {
      // FIX #9 : requestCameraPermissionsAsync est disponible via expo-image-picker
      const { status } = await ImagePicker.requestCameraPermissionsAsync();
      if (status !== 'granted') {
        return this.sendToWebView('CAMERA_PERMISSION_DENIED', {
          message: 'Permission refusée pour accéder à la caméra',
        });
      }

      const result = await ImagePicker.launchCameraAsync({
        allowsEditing: options.allowsEditing !== false,
        aspect:        options.aspect || [4, 3],
        quality:       options.quality || 0.8,
        base64: false,  // FIX #5
      });

      if (!result.canceled) {
        const asset = result.assets[0];
        this.sendToWebView('PHOTO_TAKEN', {
          uri:      asset.uri,
          width:    asset.width,
          height:   asset.height,
          mimeType: asset.mimeType || 'image/jpeg',
          fileName: asset.fileName || `photo_${Date.now()}.jpg`,
        });
      }
    } catch (err) {
      this.sendToWebView('CAMERA_ERROR', { error: err.message });
    }
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Géolocalisation
  // ──────────────────────────────────────────────────────────────────────────

  async getLocation() {
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        return this.sendToWebView('LOCATION_PERMISSION_DENIED', {
          message: 'Permission refusée pour accéder à la localisation',
        });
      }

      // Timeout de 15 s
      const locationPromise = Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      });
      const timeoutPromise = new Promise((_, reject) =>
        setTimeout(() => reject(new Error('La localisation a pris trop de temps')), 15000)
      );
      const location = await Promise.race([locationPromise, timeoutPromise]);

      this.sendToWebView('LOCATION_RECEIVED', {
        latitude:  location.coords.latitude,
        longitude: location.coords.longitude,
        accuracy:  location.coords.accuracy,
        altitude:  location.coords.altitude,
      });
    } catch (err) {
      this.sendToWebView('LOCATION_ERROR', { error: err.message });
    }
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Notifications Push  (FIX #8 : projectId requis sur Expo SDK 50+)
  // ──────────────────────────────────────────────────────────────────────────

  async registerForPushNotifications() {
    try {
      const { status: existingStatus } = await Notifications.getPermissionsAsync();
      let finalStatus = existingStatus;

      if (existingStatus !== 'granted') {
        const { status } = await Notifications.requestPermissionsAsync();
        finalStatus = status;
      }

      if (finalStatus !== 'granted') {
        return this.sendToWebView('PUSH_PERMISSION_DENIED', {
          message: 'Permission refusée pour les notifications push',
        });
      }

      // FIX #8 : projectId obligatoire sur SDK ≥ 50
      const projectId =
        Constants.expoConfig?.extra?.eas?.projectId ??
        Constants.easConfig?.projectId;

      const token = (await Notifications.getExpoPushTokenAsync({ projectId })).data;
      this.sendToWebView('PUSH_TOKEN_RECEIVED', { token });
    } catch (err) {
      this.sendToWebView('PUSH_ERROR', { error: err.message });
    }
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Listeners internes
  // ──────────────────────────────────────────────────────────────────────────

  _setupNotificationListeners() {
    // Notification reçue en foreground
    this.notificationListener = Notifications.addNotificationReceivedListener(n => {
      this.sendToWebView('NOTIFICATION_RECEIVED', {
        title: n.request.content.title,
        body:  n.request.content.body,
        data:  n.request.content.data,
      });
    });

    // Notification cliquée (background / closed)
    this.responseListener = Notifications.addNotificationResponseReceivedListener(r => {
      this.sendToWebView('NOTIFICATION_CLICKED', {
        data: r.notification.request.content.data,
      });
    });
  }
}

// Singleton — une seule instance par app
export default new NativeService();
