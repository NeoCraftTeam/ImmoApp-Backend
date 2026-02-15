import * as Camera from 'expo-camera';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import * as Notifications from 'expo-notifications';

// Configuration des notifications
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
  }),
});

/**
 * Service de gestion des fonctionnalités natives
 */
class NativeService {
  constructor() {
    this.webViewRef = null;
    this.notificationListener = null;
    this.responseListener = null;
  }

  /**
   * Initialiser le service avec la référence WebView
   */
  initialize(webViewRef) {
    this.webViewRef = webViewRef;
    this.setupNotificationListeners();
  }

  /**
   * Envoyer un message à la WebView
   */
  sendToWebView(type, data) {
    if (this.webViewRef?.current) {
      const message = JSON.stringify({ type, data });
      this.webViewRef.current.postMessage(message);
    }
  }

  /**
   * Gérer les messages reçus de la WebView
   */
  async handleWebViewMessage(event) {
    try {
      let content;
      try {
        content = JSON.parse(event.nativeEvent.data);
      } catch (e) {
        // Ignorer les messages qui ne sont pas du JSON valide (ex: logs console)
        // console.log("WebView Log:", event.nativeEvent.data);
        return;
      }

      const { type, data } = content;

      // Security: Validate origin before processing sensitive actions
      const origin = event.nativeEvent.url || '';
      const allowedOrigins = [
        'https://keyhomeback.neocraft.dev', 
        'https://api.keyhome.neocraft.dev', 
        'https://agency.keyhome.neocraft.dev',
        'https://owner.keyhome.neocraft.dev'
      ];
      
      const isAllowed = allowedOrigins.some(allowed => origin.startsWith(allowed));
      
      if (!isAllowed) {
        console.warn('Blocked message from untrusted origin:', origin);
        return;
      }

      switch (type) {
        case 'PICK_IMAGE':
          await this.pickImage(data);
          break;
        case 'TAKE_PHOTO':
          await this.takePhoto(data);
          break;
        case 'REQUEST_LOCATION':
          await this.getLocation();
          break;
        case 'REGISTER_PUSH':
          await this.registerForPushNotifications();
          break;
        default:
          console.log('Unknown message type:', type);
      }
    } catch (error) {
      console.error('Error handling WebView message:', error);
    }
  }

  /**
   * Sélectionner une image depuis la galerie
   */
  async pickImage(options = {}) {
    try {
      // Demander permission
      const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
      
      if (status !== 'granted') {
        this.sendToWebView('IMAGE_PERMISSION_DENIED', {
          message: 'Permission refusée pour accéder aux photos'
        });
        return;
      }

      // Ouvrir la galerie
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        allowsEditing: options.allowsEditing !== false,
        aspect: options.aspect || [4, 3],
        quality: options.quality || 0.8,
        base64: true, // Pour envoyer à la WebView
      });

      if (!result.canceled) {
        this.sendToWebView('IMAGE_SELECTED', {
          uri: result.assets[0].uri,
          base64: result.assets[0].base64,
          width: result.assets[0].width,
          height: result.assets[0].height,
        });
      }
    } catch (error) {
      this.sendToWebView('IMAGE_ERROR', { error: error.message });
    }
  }

  /**
   * Prendre une photo avec la caméra
   */
  async takePhoto(options = {}) {
    try {
      // Demander permission
      const { status } = await Camera.requestCameraPermissionsAsync();
      
      if (status !== 'granted') {
        this.sendToWebView('CAMERA_PERMISSION_DENIED', {
          message: 'Permission refusée pour accéder à la caméra'
        });
        return;
      }

      // Ouvrir la caméra
      const result = await ImagePicker.launchCameraAsync({
        allowsEditing: options.allowsEditing !== false,
        aspect: options.aspect || [4, 3],
        quality: options.quality || 0.8,
        base64: true,
      });

      if (!result.canceled) {
        this.sendToWebView('PHOTO_TAKEN', {
          uri: result.assets[0].uri,
          base64: result.assets[0].base64,
          width: result.assets[0].width,
          height: result.assets[0].height,
        });
      }
    } catch (error) {
      this.sendToWebView('CAMERA_ERROR', { error: error.message });
    }
  }

  /**
   * Obtenir la position géographique
   */
  async getLocation() {
    try {
      // Demander permission
      const { status } = await Location.requestForegroundPermissionsAsync();
      
      if (status !== 'granted') {
        this.sendToWebView('LOCATION_PERMISSION_DENIED', {
          message: 'Permission refusée pour accéder à la localisation'
        });
        return;
      }

      // Obtenir position
      const location = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.High,
      });

      this.sendToWebView('LOCATION_RECEIVED', {
        latitude: location.coords.latitude,
        longitude: location.coords.longitude,
        accuracy: location.coords.accuracy,
        altitude: location.coords.altitude,
      });
    } catch (error) {
      this.sendToWebView('LOCATION_ERROR', { error: error.message });
    }
  }

  /**
   * Enregistrer pour les notifications push
   */
  async registerForPushNotifications() {
    try {
      // Demander permission
      const { status: existingStatus } = await Notifications.getPermissionsAsync();
      let finalStatus = existingStatus;

      if (existingStatus !== 'granted') {
        const { status } = await Notifications.requestPermissionsAsync();
        finalStatus = status;
      }

      if (finalStatus !== 'granted') {
        this.sendToWebView('PUSH_PERMISSION_DENIED', {
          message: 'Permission refusée pour les notifications'
        });
        return;
      }

      // Obtenir le token
      const token = (await Notifications.getExpoPushTokenAsync()).data;

      this.sendToWebView('PUSH_TOKEN_RECEIVED', { token });
    } catch (error) {
      this.sendToWebView('PUSH_ERROR', { error: error.message });
    }
  }

  /**
   * Configurer les listeners de notifications
   */
  setupNotificationListeners() {
    // Notification reçue pendant que l'app est ouverte
    this.notificationListener = Notifications.addNotificationReceivedListener(notification => {
      this.sendToWebView('NOTIFICATION_RECEIVED', {
        title: notification.request.content.title,
        body: notification.request.content.body,
        data: notification.request.content.data,
      });
    });

    // Notification cliquée
    this.responseListener = Notifications.addNotificationResponseReceivedListener(response => {
      this.sendToWebView('NOTIFICATION_CLICKED', {
        data: response.notification.request.content.data,
      });
    });
  }

  /**
   * Nettoyer les listeners
   */
  cleanup() {
    if (this.notificationListener && this.notificationListener.remove) {
      this.notificationListener.remove();
    }
    if (this.responseListener && this.responseListener.remove) {
      this.responseListener.remove();
    }
  }
}

export default new NativeService();
