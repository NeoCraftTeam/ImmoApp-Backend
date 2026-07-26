import * as Localization from 'expo-localization';
import { I18n } from 'i18n-js';

import en from './en';
import fr from './fr';

/**
 * The app targets a primarily French-speaking West African audience.
 * French is the default + fallback; English is provided for the
 * estimated minority of expats / international visitors viewing
 * listings. Adding a third language only requires dropping a new
 * locale file into `src/i18n/` and importing it here.
 */
export const i18n = new I18n({
  fr,
  en,
});

i18n.defaultLocale = 'fr';
i18n.enableFallback = true;

const deviceLocale = Localization.getLocales()[0]?.languageCode ?? 'fr';
i18n.locale = deviceLocale === 'en' ? 'en' : 'fr';

/**
 * Translate helper alias — `t('home.title')` returns the active-locale
 * string. Missing keys log a console warning in dev (i18n-js default)
 * and render the key itself in production so the surface area is
 * obviously broken instead of silently empty.
 */
export function t(key: string, params?: Record<string, string | number>): string {
  return i18n.t(key, params);
}
