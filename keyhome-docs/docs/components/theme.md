---
sidebar_position: 7
title: "Theme System"
---

# Theme System

## Overview

KeyHome uses a **dual-theme design system** built on **MUI v7** with custom design tokens. The theme supports light and dark modes, with a system-preference fallback. Design tokens are defined in `src/theme/tokens.ts`, the MUI theme in `src/theme/theme.ts`, and an owner-specific override in `src/theme/ownerTheme.ts`.

---

## Design Tokens (`src/theme/tokens.ts`)

### Brand Colours

```typescript
export const brand = {
 primary: '#6B46C1', // KeyHome purple — primary brand colour
 primaryLight: '#9F7AEA', // Lighter variant for hover states
 primaryDark: '#553C9A', // Darker variant for pressed states
};
```

### Light Mode Palette

```typescript
export const light = {
 bg: '#FAFAFA', // Page background
 paper: '#FFFFFF', // Card / paper surface
 text: '#1A202C', // Primary text
 textSecondary: '#718096', // Secondary / muted text
 divider: '#E2E8F0', // Dividers and borders
 border: '#CBD5E0', // Input borders
 grey: {
 50: '#F7FAFC',
 100: '#EDF2F7',
 200: '#E2E8F0',
 300: '#CBD5E0',
 400: '#A0AEC0',
 500: '#718096',
 600: '#4A5568',
 700: '#2D3748',
 800: '#1A202C',
 900: '#171923',
 },
};
```

### Dark Mode Palette

```typescript
export const dark = {
 bg: '#0A0A0F', // Deep dark background
 paper: '#13131A', // Slightly lighter surface
 text: '#F0EEF8', // Near-white text
 errorBright: '#FC8181', // Error red (accessible on dark)
 successBright: '#68D391', // Success green (accessible on dark)
 grey: {
 50: '#1A1A24',
 100: '#1E1E2A',
 200: '#252530',
 300: '#2D2D3A',
 400: '#3A3A4A',
 500: '#5A5A70',
 600: '#8080A0',
 700: '#A0A0C0',
 800: '#C0C0D8',
 900: '#E0E0F0',
 },
};
```

### Semantic Colours

```typescript
export const semantic = {
 error: { main: '#E53E3E', light: '#FEB2B2', dark: '#C53030' },
 success: { main: '#38A169', light: '#9AE6B4', dark: '#276749' },
 warning: { main: '#DD6B20', light: '#FBD38D', dark: '#C05621' },
 info: { main: '#3182CE', light: '#BEE3F8', dark: '#2B6CB0' },
};
```

### Gradients

```typescript
export const gradient = {
 primary: 'linear-gradient(135deg, #6B46C1 0%, #9F7AEA 100%)',
 primaryHover: 'linear-gradient(135deg, #553C9A 0%, #6B46C1 100%)',
 primary135: 'linear-gradient(135deg, #6B46C1 0%, #805AD5 50%, #9F7AEA 100%)',
};
```

### Shadows

```typescript
export const shadow = {
 primaryGlow: '0 0 20px rgba(107, 70, 193, 0.4)',
 cardHover: '0 8px 30px rgba(0, 0, 0, 0.12)',
 dialog: '0 25px 50px rgba(0, 0, 0, 0.25)',
 input: '0 2px 4px rgba(0, 0, 0, 0.06)',
};
```

---

## MUI Theme (`src/theme/theme.ts`)

### Typography

```typescript
typography: {
 fontFamily: '"Inter", -apple-system, BlinkMacSystemFont, sans-serif', // Body
 h1: { fontFamily: '"Plus Jakarta Sans", sans-serif', fontWeight: 700 },
 h2: { fontFamily: '"Plus Jakarta Sans", sans-serif', fontWeight: 700 },
 h3: { fontFamily: '"Plus Jakarta Sans", sans-serif', fontWeight: 600 },
 h4: { fontFamily: '"Plus Jakarta Sans", sans-serif', fontWeight: 600 },
 h5: { fontFamily: '"Plus Jakarta Sans", sans-serif', fontWeight: 600 },
 h6: { fontFamily: '"Plus Jakarta Sans", sans-serif', fontWeight: 600 },
}
```

### Border Radius

```typescript
shape: {
 borderRadiusSmall: 8, // Chips, small components
 borderRadiusMedium: 12, // Cards, inputs
 borderRadiusLarge: 16, // Dialogs, modals
 borderRadiusPill: 99, // Pills, badges
 borderRadius: 12, // Default MUI
}
```

### Component Overrides

#### Buttons

```typescript
MuiButton: {
 styleOverrides: {
 root: {
 textTransform: 'none', // No uppercase
 fontWeight: 600,
 borderRadius: 10,
 transition: 'all 0.2s ease',
 '&:active': {
 transform: 'scale(0.96)', // Spring-like tap feedback
 },
 },
 containedPrimary: {
 background: gradient.primary,
 '&:hover': {
 background: gradient.primaryHover,
 },
 },
 },
}
```

#### Cards

```typescript
MuiCard: {
 styleOverrides: {
 root: {
 borderRadius: 16,
 transition: 'transform 0.2s ease, box-shadow 0.2s ease',
 '&:hover': {
 transform: 'translateY(-3px)', // Lift effect on hover
 boxShadow: shadow.cardHover,
 },
 },
 },
}
```

#### Inputs

```typescript
MuiOutlinedInput: {
 styleOverrides: {
 root: {
 borderRadius: 10,
 '&.Mui-focused .MuiOutlinedInput-notchedOutline': {
 borderColor: brand.primary,
 borderWidth: 2,
 },
 },
 },
}
```

#### Dialogs

```typescript
MuiDialog: {
 styleOverrides: {
 paper: {
 borderRadius: 20,
 // Custom scrollbar hidden
 '&::-webkit-scrollbar': { display: 'none' },
 },
 },
}
```

#### Icons

```typescript
MuiSvgIcon: {
 styleOverrides: {
 root: {
 transition: 'transform 0.15s ease',
 '&:hover': { transform: 'scale(1.1)' },
 },
 },
}
```

---

## ThemeProvider (`src/providers/ThemeProvider.tsx`)

Manages user theme preference with system preference detection.

### Context Interface

```typescript
interface ThemeContextType {
 mode: 'light' | 'dark'; // Resolved mode
 choice: 'light' | 'dark' | 'system'; // User's explicit choice
 toggleTheme(): void; // Toggle light/dark
 setThemeChoice(choice: ThemeChoice): void; // Set explicit choice
}
```

### Storage

- **Key**: `localStorage['theme']`
- **Values**: `'light'` | `'dark'` | `'system'`

### System Preference Detection

```typescript
const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
const resolved = choice === 'system' ? (systemDark ? 'dark' : 'light') : choice;
```

### Inline Theme Script

To prevent flash of wrong theme (FOUT), an inline script runs **before** React hydration:

```html
<!-- In src/app/layout.tsx -->
<script nonce={nonce}>
 (function() {
 const choice = localStorage.getItem('theme') || 'system';
 const dark = choice === 'dark' ||
 (choice === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
 if (dark) {
 document.documentElement.setAttribute('data-theme', 'dark');
 document.documentElement.classList.add('dark');
 }
 })();
</script>
```

### Custom `theme-change` Event

When the theme changes, a `theme-change` CustomEvent is dispatched for any non-React component (like the map) that needs to react:

```typescript
window.dispatchEvent(new CustomEvent('theme-change', { detail: { mode } }));
```

---

## Owner Theme (`src/theme/ownerTheme.ts`)

The owner panel uses a slightly different theme with:
- Higher-contrast sidebar
- Owner-specific primary accent (same purple but different component overrides)
- More compact spacing for the data-heavy dashboard

```typescript
// src/theme/ownerTheme.ts
export const ownerLightTheme = createTheme({
 ...lightTheme,
 components: {
 ...lightTheme.components,
 // Owner-specific overrides
 MuiDrawer: {
 styleOverrides: {
 paper: {
 background: '#1A1433', // Dark sidebar even in light mode
 color: '#F0EEF8',
 },
 },
 },
 },
});
```

---

## Dark Mode HTML Attributes

The `ThemeProvider` sets the following attributes on `<html>` for CSS/Tailwind dark mode compatibility:

```html
<!-- Dark mode -->
<html data-theme="dark" class="dark">

<!-- Light mode -->
<html data-theme="light">
```

Tailwind v4 dark mode uses the `class` strategy:
```css
/* Tailwind v4 dark mode */
.dark .my-element { ... }
```

---

## Tailwind CSS v4 Integration

Tailwind v4 is used for utility classes alongside MUI. The `tailwind.config.ts` uses:

```typescript
// Tailwind v4 — CSS-first configuration
// @import "tailwindcss" in global.css
// Dark mode: class-based (.dark)
// Custom colours reference MUI tokens via CSS variables
```

---

## Fonts

Loaded via `next/font/google` in `src/app/layout.tsx`:

```typescript
import { Inter, Plus_Jakarta_Sans } from 'next/font/google';

const inter = Inter({
 subsets: ['latin'],
 display: 'swap',
 variable: '--font-inter',
});

const plusJakarta = Plus_Jakarta_Sans({
 subsets: ['latin'],
 display: 'swap',
 variable: '--font-plus-jakarta',
});
```

---

## Related Documentation

- [UI Components](./ui-components.md) — Components using the theme
- [AuthProvider](./auth-provider.md) — Not theme-related
