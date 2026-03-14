import Constants from 'expo-constants';
import * as Haptics from 'expo-haptics';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import * as Notifications from 'expo-notifications';

import { ALLOWED_ORIGINS } from '../config';
import type { BridgeMessage, HapticStyle, WebViewRef } from '../types';
import BiometricService from './BiometricService';
import OAuthService from './OAuthService';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

const HAPTIC_MAP: Record<string, Haptics.ImpactFeedbackStyle | Haptics.NotificationFeedbackType> = {
  light: Haptics.ImpactFeedbackStyle.Light,
  medium: Haptics.ImpactFeedbackStyle.Medium,
  heavy: Haptics.ImpactFeedbackStyle.Heavy,
  success: Haptics.NotificationFeedbackType.Success,
  error: Haptics.NotificationFeedbackType.Error,
  warning: Haptics.NotificationFeedbackType.Warning,
};

const NOTIFICATION_HAPTICS: HapticStyle[] = ['success', 'error', 'warning'];

class NativeService {
  private webViewRef: WebViewRef | null = null;
  private notificationListener: Notifications.EventSubscription | null = null;
  private responseListener: Notifications.EventSubscription | null = null;

  initialize(webViewRef: WebViewRef): void {
    this.webViewRef = webViewRef;
    OAuthService.initialize(webViewRef);
    if (!this.notificationListener) {
      this.setupNotificationListeners();
    }
  }

  cleanup(): void {
    this.notificationListener?.remove();
    this.responseListener?.remove();
    this.notificationListener = null;
    this.responseListener = null;
    OAuthService.cleanup();
  }

  sendToWebView(type: string, data: Record<string, unknown> = {}): void {
    this.webViewRef?.current?.postMessage(JSON.stringify({ type, data }));
  }

  async handleWebViewMessage(event: { nativeEvent: { data: string; url?: string } }): Promise<void> {
    let content: BridgeMessage;
    try {
      content = JSON.parse(event.nativeEvent.data) as BridgeMessage;
    } catch {
      return;
    }

    const { type, data } = content;

    const origin = event.nativeEvent.url ?? '';
    const isAllowed = ALLOWED_ORIGINS.some((o) => origin.startsWith(o));
    if (!isAllowed && origin !== '') {
      console.warn('[NativeService] Blocked message from:', origin);
      return;
    }

    try {
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
        case 'OAUTH_SIGN_IN':
          await OAuthService.handleOAuthRequest(data);
          break;
        case 'HAPTIC':
          this.triggerHaptic((data?.style as HapticStyle) ?? 'light');
          break;
        case 'GET_BIOMETRIC_STATUS':
          await this.sendBiometricStatus();
          break;
        case 'SET_BIOMETRIC':
          await this.setBiometric(!!data?.enabled);
          break;
        case 'LOGOUT':
          await this.handleLogout();
          break;
        case 'PAGE_LOADED':
        case 'PERFORMANCE_METRICS':
        case 'APP_READY':
        case 'LOADER_SHOWN':
        case 'LOADER_HIDDEN':
        case 'MODAL_OPENED':
        case 'FOCUS_TEL_INPUT':
        case 'SET_STATUS_BAR':
          break;
        default:
          if (__DEV__) console.log('[NativeService] Unhandled:', type);
      }
    } catch (err) {
      console.error('[NativeService] Error:', err);
    }
  }

  private triggerHaptic(style: HapticStyle): void {
    const mapped = HAPTIC_MAP[style] ?? Haptics.ImpactFeedbackStyle.Light;
    if (NOTIFICATION_HAPTICS.includes(style)) {
      Haptics.notificationAsync(mapped as Haptics.NotificationFeedbackType);
    } else {
      Haptics.impactAsync(mapped as Haptics.ImpactFeedbackStyle);
    }
  }

  async pickImage(options: Record<string, unknown> = {}): Promise<void> {
    try {
      const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (status !== 'granted') {
        this.sendToWebView('IMAGE_PERMISSION_DENIED', { message: 'Permission refusée pour accéder aux photos' });
        return;
      }

      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        allowsEditing: options.allowsEditing !== false,
        aspect: (options.aspect as [number, number]) ?? [4, 3],
        quality: (options.quality as number) ?? 0.8,
        base64: false,
      });

      if (!result.canceled && result.assets[0]) {
        const asset = result.assets[0];
        this.sendToWebView('IMAGE_SELECTED', {
          uri: asset.uri,
          width: asset.width,
          height: asset.height,
          mimeType: asset.mimeType ?? 'image/jpeg',
          fileName: asset.fileName ?? `photo_${Date.now()}.jpg`,
        });
      }
    } catch (err) {
      this.sendToWebView('IMAGE_ERROR', { error: err instanceof Error ? err.message : 'Unknown error' });
    }
  }

  async takePhoto(options: Record<string, unknown> = {}): Promise<void> {
    try {
      const { status } = await ImagePicker.requestCameraPermissionsAsync();
      if (status !== 'granted') {
        this.sendToWebView('CAMERA_PERMISSION_DENIED', { message: 'Permission refusée pour accéder à la caméra' });
        return;
      }

      const result = await ImagePicker.launchCameraAsync({
        allowsEditing: options.allowsEditing !== false,
        aspect: (options.aspect as [number, number]) ?? [4, 3],
        quality: (options.quality as number) ?? 0.8,
        base64: false,
      });

      if (!result.canceled && result.assets[0]) {
        const asset = result.assets[0];
        this.sendToWebView('PHOTO_TAKEN', {
          uri: asset.uri,
          width: asset.width,
          height: asset.height,
          mimeType: asset.mimeType ?? 'image/jpeg',
          fileName: asset.fileName ?? `photo_${Date.now()}.jpg`,
        });
      }
    } catch (err) {
      this.sendToWebView('CAMERA_ERROR', { error: err instanceof Error ? err.message : 'Unknown error' });
    }
  }

  async getLocation(): Promise<void> {
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        this.sendToWebView('LOCATION_PERMISSION_DENIED', { message: 'Permission refusée pour la localisation' });
        return;
      }

      const locationPromise = Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
      const timeoutPromise = new Promise<never>((_, reject) =>
        setTimeout(() => reject(new Error('Localisation timeout')), 15_000),
      );
      const location = await Promise.race([locationPromise, timeoutPromise]);

      this.sendToWebView('LOCATION_RECEIVED', {
        latitude: location.coords.latitude,
        longitude: location.coords.longitude,
        accuracy: location.coords.accuracy,
        altitude: location.coords.altitude,
      });
    } catch (err) {
      this.sendToWebView('LOCATION_ERROR', { error: err instanceof Error ? err.message : 'Unknown error' });
    }
  }

  async registerForPushNotifications(): Promise<void> {
    try {
      const { status: existingStatus } = await Notifications.getPermissionsAsync();
      let finalStatus = existingStatus;

      if (existingStatus !== 'granted') {
        const { status } = await Notifications.requestPermissionsAsync();
        finalStatus = status;
      }

      if (finalStatus !== 'granted') {
        this.sendToWebView('PUSH_PERMISSION_DENIED', { message: 'Permission refusée pour les notifications' });
        return;
      }

      const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
      const token = (await Notifications.getExpoPushTokenAsync({ projectId })).data;
      this.sendToWebView('PUSH_TOKEN_RECEIVED', { token });
    } catch (err) {
      this.sendToWebView('PUSH_ERROR', { error: err instanceof Error ? err.message : 'Unknown error' });
    }
  }

  private setupNotificationListeners(): void {
    this.notificationListener = Notifications.addNotificationReceivedListener((n) => {
      this.sendToWebView('NOTIFICATION_RECEIVED', {
        title: n.request.content.title,
        body: n.request.content.body,
        data: n.request.content.data,
      });
    });

    this.responseListener = Notifications.addNotificationResponseReceivedListener((r) => {
      const data = r.notification.request.content.data as Record<string, unknown>;
      this.sendToWebView('NOTIFICATION_CLICKED', { data });

      if (data?.url && typeof data.url === 'string') {
        this.navigateWebViewTo(data.url);
      }
    });
  }

  private async sendBiometricStatus(): Promise<void> {
    const available = await BiometricService.isAvailable();
    const enabled = await BiometricService.isEnabled();
    const label = await BiometricService.getBiometricLabel();
    this.sendToWebView('BIOMETRIC_STATUS', { available, enabled, label });
  }

  private async setBiometric(enabled: boolean): Promise<void> {
    if (enabled) {
      const passed = await BiometricService.authenticate('Confirmez pour activer la biométrie');
      if (!passed) {
        this.sendToWebView('BIOMETRIC_SET_RESULT', { success: false, enabled: false, message: 'Authentification échouée' });
        return;
      }
    }
    await BiometricService.setEnabled(enabled);
    const label = await BiometricService.getBiometricLabel();
    this.sendToWebView('BIOMETRIC_SET_RESULT', { success: true, enabled, label });
  }

  private async handleLogout(): Promise<void> {
    await OAuthService.clearAuthToken();
    this.sendToWebView('LOGOUT_COMPLETE', {});
  }

  /**
   * Navigate the WebView to a specific path (deep link from push).
   */
  navigateWebViewTo(path: string): void {
    this.webViewRef?.current?.injectJavaScript(
      `window.location.href = '${path.replace(/'/g, "\\'")}'; true;`,
    );
  }
}

export default new NativeService();
