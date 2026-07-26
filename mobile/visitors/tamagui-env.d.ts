/**
 * Type augmentation Tamagui — isolée du runtime `tamagui.config.ts`
 * pour deux raisons :
 *
 *  1. Le plugin Babel `@tamagui/babel-plugin` `require()` le config
 *     au build-time via `esbuild-register`. Quand le fichier mélange
 *     runtime (`export default …`) et **TypeScript-only** (`declare
 *     module …`, `interface … extends … {}`), esbuild-register ne
 *     strip pas toujours proprement et le loader CJS échoue avec
 *     « Unexpected token '{' » sur le bloc `interface`.
 *
 *  2. Les fichiers `*.d.ts` ne sont jamais évalués par Node — ils
 *     sont purement consommés par TypeScript / l'IDE. Mettre toutes
 *     les déclarations `declare module` ici garantit zéro friction
 *     avec le pipeline Metro/Babel/esbuild.
 *
 * On garde `AppConfig` typé via le runtime grâce à un `typeof import`,
 * donc l'autocomplete `$brand`, `$slate900` etc. reste intacte dans
 * Tamagui.
 */

import type appConfig from './tamagui.config';

export type AppConfig = typeof appConfig;

declare module 'tamagui' {
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  interface TamaguiCustomConfig extends AppConfig {}
}
