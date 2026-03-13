import * as LocalAuthentication from 'expo-local-authentication';
import * as SecureStore from 'expo-secure-store';

const BIOMETRIC_ENABLED_KEY = 'biometric_enabled';
const BIOMETRIC_SKIPPED_KEY = 'biometric_skipped';

class BiometricService {
  /**
   * Check if the device supports biometric authentication.
   */
  async isAvailable(): Promise<boolean> {
    const compatible = await LocalAuthentication.hasHardwareAsync();
    if (!compatible) {
      return false;
    }
    const enrolled = await LocalAuthentication.isEnrolledAsync();
    return enrolled;
  }

  /**
   * Get the supported biometric types (Face ID, Touch ID, etc.).
   */
  async getSupportedTypes(): Promise<LocalAuthentication.AuthenticationType[]> {
    return LocalAuthentication.supportedAuthenticationTypesAsync();
  }

  /**
   * Get a human-readable label for the biometric type.
   */
  async getBiometricLabel(): Promise<string> {
    const types = await this.getSupportedTypes();
    if (types.includes(LocalAuthentication.AuthenticationType.FACIAL_RECOGNITION)) {
      return 'Face ID';
    }
    if (types.includes(LocalAuthentication.AuthenticationType.FINGERPRINT)) {
      return 'Empreinte digitale';
    }
    if (types.includes(LocalAuthentication.AuthenticationType.IRIS)) {
      return 'Iris';
    }
    return 'Biométrie';
  }

  /**
   * Prompt the user for biometric authentication.
   */
  async authenticate(promptMessage?: string): Promise<boolean> {
    const result = await LocalAuthentication.authenticateAsync({
      promptMessage: promptMessage ?? 'Déverrouillez KeyHome Owner',
      cancelLabel: 'Annuler',
      disableDeviceFallback: false,
      fallbackLabel: 'Utiliser le code',
    });
    return result.success;
  }

  /**
   * Check if biometric lock is enabled by the user.
   */
  async isEnabled(): Promise<boolean> {
    const value = await SecureStore.getItemAsync(BIOMETRIC_ENABLED_KEY);
    return value === 'true';
  }

  /**
   * Enable or disable biometric lock.
   */
  async setEnabled(enabled: boolean): Promise<void> {
    await SecureStore.setItemAsync(BIOMETRIC_ENABLED_KEY, enabled ? 'true' : 'false');
  }

  /**
   * Check if the user has skipped the biometric setup prompt.
   */
  async hasSkippedSetup(): Promise<boolean> {
    const value = await SecureStore.getItemAsync(BIOMETRIC_SKIPPED_KEY);
    return value === 'true';
  }

  /**
   * Mark the biometric setup as skipped.
   */
  async skipSetup(): Promise<void> {
    await SecureStore.setItemAsync(BIOMETRIC_SKIPPED_KEY, 'true');
  }

  /**
   * Full gate: returns true if the user should be allowed through.
   * - If biometrics not enabled → pass through
   * - If biometrics enabled → prompt and return result
   */
  async gate(): Promise<boolean> {
    const available = await this.isAvailable();
    if (!available) {
      return true;
    }

    const enabled = await this.isEnabled();
    if (!enabled) {
      return true;
    }

    return this.authenticate();
  }
}

export default new BiometricService();
