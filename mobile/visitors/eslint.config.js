const js = require('@eslint/js');
const tsParser = require('@typescript-eslint/parser');
const tsPlugin = require('@typescript-eslint/eslint-plugin');

/**
 * ESLint 9 flat config.
 *
 * Volontairement sans `eslint-config-expo` : le preset n'est pas installé et
 * l'ajouter tirerait react/react-hooks/import comme dépendances. On se limite
 * ici aux plugins déjà présents (@typescript-eslint) pour que `npm run lint`
 * tourne sans modifier package.json.
 */

/** Globals React Native + Jest, déclarés à la main pour éviter le paquet `globals`. */
const runtimeGlobals = {
  __DEV__: 'readonly',
  console: 'readonly',
  fetch: 'readonly',
  FormData: 'readonly',
  Blob: 'readonly',
  URL: 'readonly',
  URLSearchParams: 'readonly',
  AbortController: 'readonly',
  setTimeout: 'readonly',
  clearTimeout: 'readonly',
  setInterval: 'readonly',
  clearInterval: 'readonly',
  requestAnimationFrame: 'readonly',
  cancelAnimationFrame: 'readonly',
  global: 'readonly',
  process: 'readonly',
  require: 'readonly',
  module: 'writable',
  __dirname: 'readonly',
};

const jestGlobals = {
  describe: 'readonly',
  it: 'readonly',
  test: 'readonly',
  expect: 'readonly',
  beforeEach: 'readonly',
  afterEach: 'readonly',
  beforeAll: 'readonly',
  afterAll: 'readonly',
  jest: 'readonly',
};

module.exports = [
  {
    ignores: [
      'node_modules/**',
      'dist/**',
      'build/**',
      '.expo/**',
      '.tamagui/**',
      'coverage/**',
      'android/**',
      'ios/**',
      'expo-env.d.ts',
    ],
  },
  js.configs.recommended,
  {
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
        ecmaFeatures: { jsx: true },
      },
      globals: { ...runtimeGlobals, ...jestGlobals },
    },
    plugins: { '@typescript-eslint': tsPlugin },
    rules: {
      // Désactive les règles ESLint de base que le compilateur TS couvre déjà
      // (no-redeclare, no-undef, no-dupe-class-members…).
      ...tsPlugin.configs['flat/eslint-recommended'].rules,
      ...tsPlugin.configs.recommended.rules,
      '@typescript-eslint/no-unused-vars': [
        'warn',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],
      'no-unused-vars': 'off',
      // `require()` est la façon canonique de charger un asset statique sous Metro.
      '@typescript-eslint/no-require-imports': 'off',
    },
  },
  {
    files: ['**/*.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'commonjs',
      globals: runtimeGlobals,
    },
  },
];
