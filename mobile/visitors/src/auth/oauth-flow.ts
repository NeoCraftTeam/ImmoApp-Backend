/**
 * Indique qu'un flux OAuth « chaud » (openAuthSessionAsync / startSSOFlow)
 * est en cours dans l'app vivante. Sur Android, l'intent du deep-link de
 * retour est AUSSI routé vers l'écran `auth/callback` : sans ce drapeau,
 * l'écran et le flux chaud échangeraient tous deux le même
 * `exchange_code` à usage unique — le second échouerait en erreur visible.
 */

let active = false;

export function markOAuthFlowActive(value: boolean): void {
  active = value;
}

export function isOAuthFlowActive(): boolean {
  return active;
}
