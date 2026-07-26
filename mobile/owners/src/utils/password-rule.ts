/**
 * Règle de mot de passe alignée sur le backend :
 * Password::min(8)->mixedCase()->numbers()->symbols().
 *
 * Répliquée côté client pour éviter un 422 tardif après soumission
 * (écrans reset-password et sécurité, qui ne vérifiaient que la
 * longueur). Register a déjà cette règle via son schéma zod.
 */
export function validatePasswordRule(password: string): string | null {
  if (password.length < 8) {
    return 'Le mot de passe doit contenir au moins 8 caractères.';
  }
  if (!/[a-z]/.test(password)) {
    return 'Le mot de passe doit contenir au moins une minuscule.';
  }
  if (!/[A-Z]/.test(password)) {
    return 'Le mot de passe doit contenir au moins une majuscule.';
  }
  if (!/[0-9]/.test(password)) {
    return 'Le mot de passe doit contenir au moins un chiffre.';
  }
  if (!/[^A-Za-z0-9]/.test(password)) {
    return 'Le mot de passe doit contenir au moins un symbole.';
  }
  return null;
}
