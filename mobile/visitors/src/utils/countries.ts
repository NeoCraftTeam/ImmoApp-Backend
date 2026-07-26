/**
 * Indicatifs téléphoniques pour le sélecteur de pays des champs
 * téléphone (inscription, profil). Afrique en tête (marché KeyHome,
 * Cameroun par défaut) puis diaspora courante. Drapeaux en emoji —
 * aucune dépendance.
 */
export interface Country {
  /** Code ISO 3166-1 alpha-2. */
  code: string;
  name: string;
  /** Indicatif international, avec le préfixe `+`. */
  dial: string;
  flag: string;
}

export const DEFAULT_COUNTRY_CODE = 'CM';

export const COUNTRIES: Country[] = [
  { code: 'CM', name: 'Cameroun', dial: '+237', flag: '🇨🇲' },
  { code: 'CI', name: "Côte d'Ivoire", dial: '+225', flag: '🇨🇮' },
  { code: 'SN', name: 'Sénégal', dial: '+221', flag: '🇸🇳' },
  { code: 'GA', name: 'Gabon', dial: '+241', flag: '🇬🇦' },
  { code: 'CD', name: 'RD Congo', dial: '+243', flag: '🇨🇩' },
  { code: 'CG', name: 'Congo', dial: '+242', flag: '🇨🇬' },
  { code: 'TD', name: 'Tchad', dial: '+235', flag: '🇹🇩' },
  { code: 'CF', name: 'Centrafrique', dial: '+236', flag: '🇨🇫' },
  { code: 'GQ', name: 'Guinée équatoriale', dial: '+240', flag: '🇬🇶' },
  { code: 'BJ', name: 'Bénin', dial: '+229', flag: '🇧🇯' },
  { code: 'TG', name: 'Togo', dial: '+228', flag: '🇹🇬' },
  { code: 'BF', name: 'Burkina Faso', dial: '+226', flag: '🇧🇫' },
  { code: 'ML', name: 'Mali', dial: '+223', flag: '🇲🇱' },
  { code: 'NE', name: 'Niger', dial: '+227', flag: '🇳🇪' },
  { code: 'GN', name: 'Guinée', dial: '+224', flag: '🇬🇳' },
  { code: 'MR', name: 'Mauritanie', dial: '+222', flag: '🇲🇷' },
  { code: 'NG', name: 'Nigeria', dial: '+234', flag: '🇳🇬' },
  { code: 'GH', name: 'Ghana', dial: '+233', flag: '🇬🇭' },
  { code: 'KE', name: 'Kenya', dial: '+254', flag: '🇰🇪' },
  { code: 'RW', name: 'Rwanda', dial: '+250', flag: '🇷🇼' },
  { code: 'BI', name: 'Burundi', dial: '+257', flag: '🇧🇮' },
  { code: 'ET', name: 'Éthiopie', dial: '+251', flag: '🇪🇹' },
  { code: 'TZ', name: 'Tanzanie', dial: '+255', flag: '🇹🇿' },
  { code: 'UG', name: 'Ouganda', dial: '+256', flag: '🇺🇬' },
  { code: 'ZA', name: 'Afrique du Sud', dial: '+27', flag: '🇿🇦' },
  { code: 'MA', name: 'Maroc', dial: '+212', flag: '🇲🇦' },
  { code: 'DZ', name: 'Algérie', dial: '+213', flag: '🇩🇿' },
  { code: 'TN', name: 'Tunisie', dial: '+216', flag: '🇹🇳' },
  { code: 'EG', name: 'Égypte', dial: '+20', flag: '🇪🇬' },
  { code: 'FR', name: 'France', dial: '+33', flag: '🇫🇷' },
  { code: 'BE', name: 'Belgique', dial: '+32', flag: '🇧🇪' },
  { code: 'CH', name: 'Suisse', dial: '+41', flag: '🇨🇭' },
  { code: 'DE', name: 'Allemagne', dial: '+49', flag: '🇩🇪' },
  { code: 'IT', name: 'Italie', dial: '+39', flag: '🇮🇹' },
  { code: 'ES', name: 'Espagne', dial: '+34', flag: '🇪🇸' },
  { code: 'PT', name: 'Portugal', dial: '+351', flag: '🇵🇹' },
  { code: 'GB', name: 'Royaume-Uni', dial: '+44', flag: '🇬🇧' },
  { code: 'US', name: 'États-Unis', dial: '+1', flag: '🇺🇸' },
  { code: 'CA', name: 'Canada', dial: '+1', flag: '🇨🇦' },
  { code: 'AE', name: 'Émirats arabes unis', dial: '+971', flag: '🇦🇪' },
  { code: 'CN', name: 'Chine', dial: '+86', flag: '🇨🇳' },
  { code: 'TR', name: 'Turquie', dial: '+90', flag: '🇹🇷' },
];

/**
 * Découpe un numéro déjà stocké (`+237650000001`) en pays + partie
 * locale. Les indicatifs les plus longs sont testés d'abord pour ne
 * pas confondre +23 et +237. Sans correspondance : pays par défaut,
 * numéro rendu tel quel.
 */
export function splitPhoneNumber(value: string): {
  country: Country;
  local: string;
} {
  const fallback =
    COUNTRIES.find((c) => c.code === DEFAULT_COUNTRY_CODE) ?? COUNTRIES[0]!;
  const trimmed = value.trim();
  if (!trimmed.startsWith('+')) {
    return { country: fallback, local: trimmed };
  }
  const matches = COUNTRIES.filter((c) => trimmed.startsWith(c.dial)).sort(
    (a, b) => b.dial.length - a.dial.length,
  );
  const match = matches[0];
  if (!match) {
    return { country: fallback, local: trimmed };
  }
  return { country: match, local: trimmed.slice(match.dial.length).trim() };
}
