// @ts-check
/**
 * Jest config minimale pour l'app owner.
 *
 * On évite `jest-expo` ici (ne pas tirer toute la machinerie RN dans
 * des tests purs de logique) en utilisant le préset TS natif. Tous nos
 * tests sont des units sur du code pur (utils, selects de hooks) — ils
 * n'instancient aucun composant RN.
 */
module.exports = {
  preset: 'ts-jest',
  testEnvironment: 'node',
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/src/$1',
  },
  testPathIgnorePatterns: ['/node_modules/', '/e2e/'],
  collectCoverageFrom: ['src/hooks/**/*.ts', 'src/utils/**/*.ts', 'src/api/**/*.ts', '!**/*.d.ts'],
  transform: {
    '^.+\\.(ts|tsx)$': ['ts-jest', { tsconfig: { jsx: 'react', module: 'commonjs', target: 'es2020', moduleResolution: 'node', esModuleInterop: true } }],
  },
};
