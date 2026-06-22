import * as Localization from 'expo-localization';
import { I18n } from 'i18n-js';

import en from './en';
import fr from './fr';

/**
 * The owner app targets a primarily French-speaking West African
 * audience (bailleurs et agents immobiliers). French is the default +
 * fallback; English is provided for the minority of international
 * agents.
 */
export const i18n = new I18n({ fr, en });

i18n.defaultLocale = 'fr';
i18n.enableFallback = true;

const deviceLocale = Localization.getLocales()[0]?.languageCode ?? 'fr';
i18n.locale = deviceLocale === 'en' ? 'en' : 'fr';

export function t(key: string, params?: Record<string, string | number>): string {
  return i18n.t(key, params);
}
