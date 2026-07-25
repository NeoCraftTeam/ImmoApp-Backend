import axios from 'axios';

export type ApiErrorPayload = {
  message?: string;
  code?: string;
  email_verification_required?: boolean;
  email?: string;
};

export function parseApiErrorPayload(err: unknown): ApiErrorPayload | null {
  if (!axios.isAxiosError(err)) {
    return null;
  }

  const data = err.response?.data;
  if (!data || typeof data !== 'object') {
    return null;
  }

  const record = data as Record<string, unknown>;

  return {
    message: typeof record.message === 'string' ? record.message : undefined,
    code: typeof record.code === 'string' ? record.code : undefined,
    email_verification_required: record.email_verification_required === true,
    email: typeof record.email === 'string' ? record.email : undefined,
  };
}

/** Statut HTTP d'une erreur Axios (null si pas de réponse / non-Axios). */
export function getApiErrorStatus(err: unknown): number | null {
  if (axios.isAxiosError(err)) {
    return err.response?.status ?? null;
  }

  return null;
}

function panelMismatchMessage(): string {
  return 'Ce compte n’est pas autorisé sur cette application. Utilisez l’app KeyHome Visiteur pour un compte client, ou l’app KeyHome Propriétaire pour un compte bailleur.';
}

/**
 * Message utilisateur pour les échecs de connexion / auth API.
 * Distingue mauvaise app (client vs bailleur), rate-limit et e-mail non vérifié
 * du message générique « Identifiants incorrects ».
 */
export function extractAuthErrorMessage(err: unknown): string {
  const payload = parseApiErrorPayload(err);
  const status = getApiErrorStatus(err);

  if (
    payload?.code === 'PANEL_ACCESS_DENIED'
    || payload?.code === 'ROLE_CONTEXT_MISMATCH'
  ) {
    return panelMismatchMessage();
  }

  if (payload?.code === 'RATE_LIMITED' || status === 429) {
    return payload?.message ?? 'Trop de tentatives. Patientez quelques minutes avant de réessayer.';
  }

  if (payload?.email_verification_required) {
    return payload.message ?? 'Vérifiez votre adresse e-mail pour continuer.';
  }

  return extractApiErrorMessage(err);
}

/**
 * Pulls the API's standard `{ message, errors }` shape into a usable string.
 */
export function extractApiErrorMessage(err: unknown): string {
  if (axios.isAxiosError(err)) {
    const payload = parseApiErrorPayload(err);
    const data = err.response?.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined;

    if (data?.errors) {
      const first = Object.values(data.errors)[0]?.[0];
      if (typeof first === 'string' && first.trim() !== '') {
        return first;
      }
    }

    if (
      payload?.code === 'PANEL_ACCESS_DENIED'
      || payload?.code === 'ROLE_CONTEXT_MISMATCH'
    ) {
      return panelMismatchMessage();
    }

    const msg = data?.message;
    if (typeof msg === 'string' && msg.trim() !== '') {
      if (/oauth\/github\/redirect could not be found/i.test(msg)) {
        return 'Connexion GitHub indisponible sur ce serveur. Utilisez Google, Facebook ou e-mail + mot de passe, ou mettez à jour l’API.';
      }

      return msg;
    }

    if (err.response?.status === 401) {
      return 'Identifiants incorrects. Si vous vous connectez sur le web via Google, utilisez « Continuer avec Google » ici, ou réinitialisez votre mot de passe sur keyhome.app.';
    }

    if (err.response?.status === 403) {
      return 'Action non autorisée.';
    }

    if (err.response?.status === 422) {
      return 'Données invalides.';
    }

    if (err.response?.status === 429) {
      return 'Trop de tentatives. Patientez quelques minutes avant de réessayer.';
    }

    if (err.code === 'ECONNABORTED') {
      return 'Délai d’attente dépassé. Vérifiez votre connexion.';
    }

    if (!err.response) {
      const code = err.code ?? 'ERR_NETWORK';
      return `Connexion au serveur impossible (${code}). Vérifiez votre connexion internet.`;
    }
  }

  if (err instanceof Error && err.message.trim() !== '') {
    return err.message;
  }

  return 'Une erreur est survenue. Réessayez plus tard.';
}
