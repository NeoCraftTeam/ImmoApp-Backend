// Mock global AsyncStorage — le préset jest-expo ne le fournit pas, et
// plusieurs modules (currency, credit-purchase…) l'importent directement.
jest.mock(
  '@react-native-async-storage/async-storage',
  () => require('@react-native-async-storage/async-storage/jest/async-storage-mock'),
);
