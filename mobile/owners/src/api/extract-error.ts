import axios from 'axios';

/**
 * Pulls the API's standard `{ message, errors }` shape (Laravel
 * form-request validation responses) into a usable string for toast /
 * inline rendering. Module isolé du client Axios pour pouvoir être
 * testé en isolation (Node) sans tirer les deps natives Expo.
 */
export function extractApiErrorMessage(err: unknown): string {
  if (axios.isAxiosError(err)) {
    const data = err.response?.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined;
    if (data?.errors) {
      const first = Object.values(data.errors)[0]?.[0];
      if (typeof first === 'string' && first.trim() !== '') {
        return first;
      }
    }
    const msg = data?.message;
    if (typeof msg === 'string' && msg.trim() !== '') {
      return msg;
    }
    if (err.response?.status === 401) {
      return 'Identifiants incorrects.';
    }
    if (err.response?.status === 403) {
      return 'Action non autorisée.';
    }
    if (err.response?.status === 422) {
      return 'Données invalides.';
    }
    if (err.code === 'ECONNABORTED') {
      return 'Délai d’attente dépassé. Vérifiez votre connexion.';
    }
    if (!err.response) {
      const code = err.code ?? 'ERR_NETWORK';
      return `Connexion au serveur impossible (${code}). Vérifiez votre réseau.`;
    }
  }
  if (err instanceof Error && err.message.trim() !== '') {
    return err.message;
  }
  return 'Une erreur est survenue. Réessayez plus tard.';
}
