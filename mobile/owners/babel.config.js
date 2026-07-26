// @ts-check
/**
 * Tamagui's babel plugin extracts styled components at build time so
 * runtime style resolution is replaced by a flat StyleSheet. Without it
 * the app still works but loses ~30% of the perf win Tamagui exists for.
 *
 * The Reanimated worklets transform is intentionally NOT included: this
 * app uses React Native's built-in `Animated` API with `useNativeDriver`
 * for every animation. Adding the worklets plugin without a compatible
 * native module in Expo Go blew up the bundle in the visitor app — keep
 * it omitted unless a screen explicitly imports `react-native-reanimated`.
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
    ],
  };
};
