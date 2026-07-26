/**
 * User-facing copy for Clerk Expo failures (non-Axios errors from @clerk/clerk-expo).
 */
export function isClerkAlreadySignedInError(err: unknown): boolean {
  const raw =
    err instanceof Error
      ? err.message
      : typeof err === 'string'
        ? err
        : '';

  return /already signed in/i.test(raw);
}

export function formatClerkAuthError(
  err: unknown,
  provider: 'google' | 'facebook' | 'github' = 'google',
): string {
  const raw =
    err instanceof Error
      ? err.message
      : typeof err === 'string'
        ? err
        : '';

  const redirectMismatch = raw.match(
    /authorized redirect URI[^.]*\.?\s*(exp:\/\/[^\s]+|keyhome:\/\/[^\s]+)/i,
  );
  if (/redirect url passed in the sign in/i.test(raw) || redirectMismatch) {
    const url =
      redirectMismatch?.[1]
      ?? raw.match(/(exp:\/\/[^\s]+|keyhome:\/\/[^\s]+)/i)?.[1]
      ?? 'keyhome://auth/callback';

    return [
      'Cette URL de retour n’est pas autorisée dans Clerk.',
      '',
      'Dashboard Clerk → Native applications → Allowlist for mobile SSO redirect → + Add redirect URL :',
      '',
      url,
      '',
      'En Expo Go l’URL change si l’IP ou le port Metro change — recopiez celle du message d’erreur.',
    ].join('\n');
  }

  if (/native api is disabled/i.test(raw)) {
    return [
      `${providerLabel(provider)} via Clerk nécessite l’activation de l’API Native dans le tableau de bord Clerk.`,
      '',
      '1. dashboard.clerk.com → Native applications → activer « Native API »',
      '2. Enregistrer l’app iOS (bundle : com.keyhome.visitors)',
      '3. Ajouter l’URL de retour : keyhome://auth/callback',
    ].join('\n');
  }

  if (/already signed in/i.test(raw)) {
    return 'Une session est encore ouverte côté Clerk. Fermez l’app et réessayez, ou utilisez e-mail + mot de passe.';
  }

  if (raw.trim() !== '') {
    return raw;
  }

  return `Connexion ${providerLabel(provider)} impossible. Réessayez ou utilisez e-mail + mot de passe.`;
}

function providerLabel(provider: 'google' | 'facebook' | 'github'): string {
  switch (provider) {
    case 'google':
      return 'Google';
    case 'facebook':
      return 'Facebook';
    case 'github':
      return 'GitHub';
  }
}
