// @ts-check
const { getDefaultConfig } = require('expo/metro-config');

/** @type {import('expo/metro-config').MetroConfig} */
const config = getDefaultConfig(__dirname);

// Tamagui ships ESM + CJS; resolve to CJS in Metro to avoid the dual-package
// hazard that breaks `tamagui` v1 in some hermes builds.
config.resolver.unstable_enablePackageExports = false;

module.exports = config;
