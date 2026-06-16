// @ts-check
/**
 * Tamagui's babel plugin extracts styled components at build time so
 * runtime style resolution is replaced by a flat StyleSheet. Without it
 * the app still works but loses ~30% of the perf win Tamagui exists for.
 *
 * `reanimated/plugin` must be LAST per Reanimated docs.
 */
module.exports = function (api) {
  api.cache(true);
  return {
    presets: ['babel-preset-expo'],
    plugins: [
      [
        '@tamagui/babel-plugin',
        {
          components: ['tamagui'],
          config: './tamagui.config.ts',
          logTimings: true,
        },
      ],
      'react-native-reanimated/plugin',
    ],
  };
};
