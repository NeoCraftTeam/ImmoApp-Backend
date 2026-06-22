/**
 * Type augmentation Tamagui — isolée du runtime `tamagui.config.ts`.
 * Voir le commentaire jumeau dans `mobile/visitors/tamagui-env.d.ts`
 * pour la raison (esbuild-register + `declare module` → "Unexpected
 * token '{'" au build-time du babel-plugin Tamagui).
 */

import type appConfig from './tamagui.config';

export type AppConfig = typeof appConfig;

declare module 'tamagui' {
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  interface TamaguiCustomConfig extends AppConfig {}
}
