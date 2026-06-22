// @ts-check
/**
 * Jest config — préset `jest-expo` gère les transforms RN/Expo (Babel
 * + module resolution) et fournit les mocks natifs (SecureStore,
 * Notifications, Constants…).
 *
 * On laisse `transformIgnorePatterns` au préset — il whitelist déjà
 * tout l'écosystème Expo/RN. Tenter de l'override ici cause un
 * "SyntaxError: Cannot use import statement outside a module" sur
 * `expo-modules-core` parce qu'on perd les fichiers du préset.
 */
module.exports = {
  preset: 'jest-expo',
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/src/$1',
  },
  testPathIgnorePatterns: ['/node_modules/', '/e2e/'],
  collectCoverageFrom: [
    'src/hooks/**/*.ts',
    'src/utils/**/*.ts',
    'src/api/**/*.ts',
    '!**/*.d.ts',
  ],
};
