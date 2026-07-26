/**
 * Trace le token push Expo enregistré auprès du backend pour pouvoir le
 * désenregistrer au signOut (DELETE /fcm/token exige le token exact).
 * Sans ça, l'appareil continue de recevoir les notifications de
 * l'ancien compte après déconnexion.
 */

let registeredPushToken: string | null = null;

export function setRegisteredPushToken(token: string | null): void {
  registeredPushToken = token;
}

export function getRegisteredPushToken(): string | null {
  return registeredPushToken;
}
