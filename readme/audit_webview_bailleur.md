# Audit — `mobile/bailleur` React Native WebView Implementation

> **Date:** 2026-02-23 | **Auditor:** Antigravity  
> **Files analysed:** `App.js` (421 ln) · `services/NativeService.js` (287 ln) · `services/OAuthService.js` (300 ln) · `app.json` (65 ln) · `resources/js/filament-native-bridge.js` (44 ln) · `app/Providers/Filament/BailleurPanelProvider.php`

---

## Executive Summary

The bailleur mobile app is a **WebView shell** wrapping the Filament `/owner` panel. The architecture is sound and several past fixes (memory leaks, base64 avoidance, projectId for push, native client IDs for OAuth) are correctly implemented. However **three blocking bugs** prevent the app from working correctly in production, one **high-severity security gap** exposes auth tokens, and **iOS App Store submission will be rejected** in its current state without minor changes.

| Category | Score | Key finding |
|----------|-------|-------------|
| Native integration | 6/10 | Bridge functional but MODAL_OPENED / FOCUS_TEL_INPUT unhandled on native side |
| Performance | 7.5/10 | Good, but `loadingOpacity` ref is dead weight; no progressive rendering |
| Security | 5.5/10 | Token sent via postMessage, `NSAllowsArbitraryLoadsInWebContent=true`, origin check bypass |
| Deployment readiness | 5/10 | Critical: wrong OAuth scheme, missing Android permission, ATS issue |
| Bugs | — | 3 blocking bugs, 4 medium bugs |

---

## 1. Native Component Utilisation Assessment

### ✅ What is correctly integrated

| Bridge message | Native handler | Status |
|----------------|---------------|--------|
| `PICK_IMAGE` | `ImagePicker.launchImageLibraryAsync` | ✅ Working |
| `TAKE_PHOTO` | `ImagePicker.launchCameraAsync` | ✅ Working |
| `REQUEST_LOCATION` | `Location.getCurrentPositionAsync` (15 s timeout) | ✅ Working |
| `REGISTER_PUSH` | `Notifications.getExpoPushTokenAsync` | ✅ Working |
| `OAUTH_SIGN_IN` | `expo-auth-session` + PKCE | ⚠️ Broken (see §5) |
| Android Back button | `BackHandler.addEventListener` | ✅ Working |
| Offline detection | `NetInfo.addEventListener` | ✅ Working |
| Haptic on retry | `Haptics.impactAsync` | ✅ Working |
| Safe area insets | `SafeAreaProvider` + `BailleurPanelProvider` CSS env() | ✅ Working |
| Dynamic Island / notch support | `viewport-fit=cover` + `env(safe-area-inset-top)` | ✅ Working |

### ⚠️ Incomplete integrations

**1. `MODAL_OPENED` / `FOCUS_TEL_INPUT` — sent but never handled**  
`filament-native-bridge.js` sends these events to the native side (`sendToNative('MODAL_OPENED')`, `sendToNative('FOCUS_TEL_INPUT', ...)`), but **`NativeService.handleWebViewMessage` has no `case` for either type**. They silently fall through to `default: console.log(...)`.

```js
// NativeService.js switch — missing cases:
case 'MODAL_OPENED':   // Not present → Android back button won't intercept modal close
case 'FOCUS_TEL_INPUT': // Not present → no native keyboard optimisation
```

**Impact:** On Android, pressing the hardware back button while a Filament slide-over is open will exit the app instead of closing the modal.

**Fix:**
```js
case 'MODAL_OPENED':
  // Push a modal-open state so BackHandler can pop it
  this.modalStack = (this.modalStack || 0) + 1;
  break;
case 'MODAL_CLOSED':
  this.modalStack = Math.max(0, (this.modalStack || 0) - 1);
  break;
case 'FOCUS_TEL_INPUT':
  // No-op on iOS; on Android could trigger custom keyboard
  break;
```

**2. `PAGE_LOADED` event — not used for navigation progress**  
`filament-native-bridge.js` emits `PAGE_LOADED` on every Filament page load, but `NativeService` ignores it. This could reset the loading spinner or pre-warm the next screen.

**3. No `pull-to-refresh`**  
`pullToRefreshEnabled={false}` is intentional but no alternative gesture-based refresh is offered. For a property management panel, users will expect a visible refresh mechanism.

**4. No deep-link / URL scheme handling**  
`app.json` registers the `keyhome-bailleur` scheme for OAuth callbacks, but there is no `Linking` listener in `App.js` to handle incoming deep links (e.g., email verification clicking opens the app to the right panel URL).

---

## 2. Optimization Review

### ✅ Good practices observed
- `useNativeDriver: true` on all animations (splash spring, fade-out) — GPU-accelerated, no JS thread blocking.
- `base64: false` in `ImagePicker` — avoids serialising large binary blobs over the bridge.
- `cacheEnabled={true}` + `cacheMode="LOAD_DEFAULT"` — respects HTTP cache headers from the Filament backend.
- `isFirstLoad` guard prevents the loading spinner from reappearing on every WebView navigation (SPA-feel).
- Timer ref cleanup (`clearTimeout(splashTimer.current)`) and `NativeService.cleanup()` prevent memory leaks on unmount.

### ⚠️ Sub-optimal / bottlenecks

**1. Dead `loadingOpacity` animated value**
```js
// Line 66 — declared but never used in JSX or animations
const loadingOpacity = useRef(new Animated.Value(1)).current;
```
Minor, but creates an unnecessary Animated node and a React Native animation driver subscription.

**2. `onNavigationStateChange` triggers on every URL change**
```js
onNavigationStateChange={(s) => setCanGoBack(s.canGoBack)}
```
This calls `setState` on every internal navigation within the Filament SPA, causing a full React re-render of `App.js` (and re-computing all `useCallback` closures) for a boolean change. Use a ref instead:
```js
const canGoBackRef = useRef(false);
// in BackHandler: if (canGoBackRef.current && webViewRef.current) ...
onNavigationStateChange={(s) => { canGoBackRef.current = s.canGoBack; }}
```

**3. Splash timer is always 600 ms, regardless of actual load time**
```js
splashTimer.current = setTimeout(hideSplash, 600);
```
If the page loads in 200 ms, the splash delays an extra 400 ms unnecessarily. If the page loads in 2 s, the splash disappears before the content is ready, revealing a blank WebView. The delay should adapt to actual content readiness:
```js
// Remove fixed timeout; use onLoadEnd directly:
const handleLoadEnd = useCallback(() => {
  setIsLoading(false);
  setIsFirstLoad(false);
  hideSplash(); // immediate, no timeout
}, [...]);
```

**4. Location accuracy: `Balanced` may be too low for property positioning**
```js
accuracy: Location.Accuracy.Balanced  // ~100 m
```
For listing a property's exact GPS coordinates, `Location.Accuracy.High` (< 10 m) is more appropriate, at the cost of slightly more battery. Offer this only when the user taps "Localiser mon bien".

**5. `MutationObserver` in `filament-native-bridge.js` observes the entire `document.body` with `subtree: true`**
This is an expensive observer for a full Filament SPA that frequently mutates the DOM (Livewire re-renders). Add a debounce or limit to a specific container:
```js
// Cheap approach: observe only the fi-modal-container
const root = document.getElementById('fi-modal-root') || document.body;
observer.observe(root, { childList: true, subtree: false });
```

---

## 3. Security Audit

### 🔴 High — Auth token propagated via unencrypted postMessage (OAuthService L224)

```js
// OAuthService.js line 224
this.sendToWebView('OAUTH_SUCCESS', {
  user: backendResult.user,
  token: backendResult.token,  // ← Sanctum Bearer token in plain postMessage
});
```

`postMessage` from native to WebView **is not encrypted**. On Android, a malicious app with `READ_LOGS` permission or a rooted device can intercept these messages via logcat. The token should never travel through the bridge in plaintext.

**Recommended fix:** Instead of passing the token in the bridge message, store it in `expo-secure-store` (already implemented in `storeAuthToken`) and let the WebView trigger a `/auth/clerk/exchange` or session-cookie–based login by injecting a one-time code:
```js
// Store token natively, inject only a one-time session nonce
await this.storeAuthToken(backendResult.token);
this.sendToWebView('OAUTH_SUCCESS', { user: backendResult.user });
// WebView reads cookie/session from the HTTP response directly
```

### 🔴 High — `NSAllowsArbitraryLoadsInWebContent = true` (iOS ATS bypass)

```json
// app.json line 20
"NSAllowsArbitraryLoadsInWebContent": true
```

This disables Apple Transport Security for **all web content** loaded in WKWebView, not just the exception domains. Combined with `originWhitelist={['https://*', 'http://localhost:*']}` it allows the WebView to load any HTTPS page — but the ATS bypass means insecure mixed content can also load.

**Fix:** Remove `NSAllowsArbitraryLoadsInWebContent` entirely. The `NSExceptionDomains` entries already handle the known hostnames. `NSExceptionAllowsInsecureHTTPLoads: false` is already set for the production domain.

```json
// Remove this line:
"NSAllowsArbitraryLoadsInWebContent": true,
```

### 🟠 Medium — Origin check uses `event.nativeEvent.url` (full URL, not host)

```js
// NativeService.js line 98
const origin = event.nativeEvent.url || '';
const isAllowed = ALLOWED_ORIGINS.some(o => origin.startsWith(o));
```

`event.nativeEvent.url` is the **current page URL** (e.g., `https://api.keyhome.neocraft.dev/owner/ads/create`), not the origin of the postMessage sender. This check is bypassable: a URL like `https://api.keyhome.neocraft.dev.evil.com/owner` would NOT pass (startsWith is correct), but any arbitrary message payload from the page (e.g., a malicious ad/listing containing a script) that calls `window.KeyHomeBridge.pickImage()` would pass because the check is on the page URL, not the sender.

The real protection is that `javaScriptEnabled=true` with CSP headers on the Filament panel is needed server-side. Confirm that `Content-Security-Policy` headers are set on the backend.

**Recommended additional hardening:**
```js
// More robust: parse URL to extract exact origin
const { hostname, protocol } = new URL(origin);
const isAllowed = ALLOWED_ORIGINS.some(o => {
  const { hostname: h } = new URL(o);
  return hostname === h && protocol === 'https:';
});
```

### 🟠 Medium — `keyhome.test` development domain left in production ATS config

```json
"keyhome.test": {
  "NSExceptionAllowsInsecureHTTPLoads": true,
  ...
}
```

This exception allows insecure HTTP loads for the `keyhome.test` domain in the production App Store binary. Strip this before release.

### 🟡 Low — `console.error` / `console.warn` in production build

Multiple places log sensitive details:
```js
console.error('Google Sign-In error:', error);       // OAuthService L116
console.warn('[WebView] HTTP Error 500 on ${url}');  // App.js L135
console.error('[NativeService] Erreur non gérée:', err); // NativeService L117
```

On a non-minified Expo dev build these appear in device logs. In production, replace with `Sentry.captureException(err)` and suppress console output.

### 🟡 Low — `expo-secure-store` token not cleared on session expiry

`storeAuthToken` stores the Sanctum token but there is no mechanism to clear it when the backend returns a 401 or when the WebView navigates to `/login`. If a token expires server-side, the app will repeatedly attempt OAuth with a stale stored token.

---

## 4. Deployment Readiness Evaluation

### ❌ Blocking issues for App Store submission

**1. Wrong OAuth redirect scheme in OAuthService**
```js
// OAuthService.js line 68 (shared from agency)
redirectUri: AuthSession.makeRedirectUri({
  scheme: 'keyhome-agency',  // ← Should be 'keyhome-bailleur' for the bailleur app
  path: 'oauth/callback',
}),
```
`OAuthService.js` is **physically copied from the Agency app** (or shared) but **uses `keyhome-agency`** scheme. The bailleur `app.json` defines `keyhome-bailleur` as the scheme. The OAuth redirect will fail silently on both iOS and Android.

**Fix:**
```js
scheme: process.env.EXPO_PUBLIC_APP_SCHEME || 'keyhome-bailleur',
```

**2. Missing `READ_MEDIA_IMAGES` Android permission**
`app.json` declares `CAMERA`, `ACCESS_FINE_LOCATION`, `ACCESS_COARSE_LOCATION` but **not** `READ_MEDIA_IMAGES` (Android 13+) or `READ_EXTERNAL_STORAGE` (Android ≤ 12). `ImagePicker.requestMediaLibraryPermissionsAsync` will fail at runtime on Android 13+ devices.

```json
"permissions": [
  "CAMERA",
  "ACCESS_FINE_LOCATION",
  "ACCESS_COARSE_LOCATION",
  "READ_MEDIA_IMAGES",
  "READ_EXTERNAL_STORAGE"
]
```

**3. `expo-image-picker` and `expo-location` plugins not declared in `app.json`**
```json
"plugins": [
  "expo-secure-store",
  "expo-web-browser"
  // Missing: "expo-image-picker", "expo-location", "expo-notifications"
]
```
Without these plugins, Expo's prebuild / EAS Build will not inject the correct iOS `Info.plist` entries and Android manifest permissions. The privacy strings (`NSCameraUsageDescription` etc.) are set manually in `infoPlist` — but the plugin approach is required for the standard EAS pipeline.

### ⚠️ Deployment concerns (non-blocking but advisable)

| Issue | Recommendation |
|-------|---------------|
| No privacy policy URL in app | Apple requires an in-app privacy policy link. Add it to the splash or settings screen. |
| No crash reporting | No Sentry SDK configured in the mobile app. Add `@sentry/react-native` for production crash visibility. |
| `userInterfaceStyle: "light"` only | No dark mode support. Will look jarring on iOS dark mode devices. |
| Version `1.0.0` with no `buildNumber`/`versionCode` | App Store will reject without explicit build numbers. |
| No `bundleIdentifier` for iOS | `app.json` sets `android.package` but omits `ios.bundleIdentifier`. EAS Build will fail. |

---

## 5. Bug & Vulnerability Inventory

### 🔴 BUG-01 — `config` is undefined in OAuthService (runtime crash)

**File:** `OAuthService.js` **Line:** 82  
**Severity:** Critical — OAuth flow crashes on every attempt

```js
const tokenResponse = await AuthSession.exchangeCodeAsync(
  {
    clientId: config.webClientId,  // ← `config` is not defined in this scope
    //         ^^^^^^ ReferenceError: config is not defined
```

`config` should be `this.config`. This is a copy-paste bug. The `signInWithGoogle` method exists in the non-class context of the original code and was refactored into a class without updating all references.

**Fix:**
```js
clientId: this.config.googleClientIdWeb,
```

---

### 🔴 BUG-02 — OAuth scheme mismatch causes silent redirect failure

**File:** `OAuthService.js` **Line:** 68  
**Severity:** Critical — OAuth cannot complete; user stuck

The `redirectUri` uses `scheme: 'keyhome-agency'`. The bailleur app's deep link scheme should be `keyhome-bailleur` (as per `app.json` convention). Google will redirect to `keyhome-agency://oauth/callback` which is not registered in the bailleur app — the OS will not intercept it.

---

### 🔴 BUG-03 — 4xx HTTP errors silently ignored

**File:** `App.js` **Lines:** 132–145  
**Severity:** High — Users see a blank WebView on 401/403/404

```js
const handleHttpError = useCallback((syntheticEvent) => {
  const { statusCode } = syntheticEvent.nativeEvent;
  if (statusCode >= 500) {   // ← 4xx completely ignored!
    setError({ ... });
  }
}, []);
```

If the Filament session expires (401) or a resource is forbidden (403), the WebView silently renders a blank white page. No error screen, no retry. Add:
```js
if (statusCode === 401) {
  // Redirect to login within WebView
  webViewRef.current?.injectJavaScript(`window.location.href = '${APP_CONFIG.baseUrl}/login';`);
} else if (statusCode >= 400) {
  setError({ type: 'client', code: statusCode, message: `Erreur ${statusCode}`, details: '...' });
}
```

---

### 🟠 BUG-04 — `isFirstLoad` state causes `handleLoadEnd` stale closure during splash

**File:** `App.js` **Lines:** 117–126  
**Severity:** Medium — In rapid network conditions, loading state can desync

`handleLoadEnd` captures `isFirstLoad` and `showSplash` in a `useCallback` with those deps. When both are true simultaneously (race condition on ultra-fast network), `hideSplash()` can be called twice: once from `loadEnd` and once from a timer already scheduled from a previous load cycle due to the timer not being cancelled before scheduling.

---

### 🟠 BUG-05 — Location timeout `Promise.race` rejects without cleanup

**File:** `NativeService.js` **Lines:** 211–217  
**Severity:** Medium — GPS watcher may leak on timeout

```js
const locationPromise = Location.getCurrentPositionAsync({...});
const timeoutPromise = new Promise((_, reject) =>
  setTimeout(() => reject(...), 15000)
);
const location = await Promise.race([locationPromise, timeoutPromise]);
```

When the timeout wins, `locationPromise` is still pending and holds an internal GPS subscription. The Location module will eventually resolve it, but the result is silently discarded. Use `Location.watchPositionAsync` with explicit `remove()` or `Location.getLastKnownPositionAsync` as fallback.

---

### 🟡 BUG-06 — `sendToNative` in `filament-native-bridge.js` sends **to native**, not received by `NativeService`

**File:** `resources/js/filament-native-bridge.js` **Lines:** 13–17  
**Severity:** Low — Events are posted but some are never consumed

`window.sendToNative` calls `window.ReactNativeWebView.postMessage(...)` which correctly reaches `NativeService.handleWebViewMessage`. However `MODAL_OPENED` and `FOCUS_TEL_INPUT` are in the `default:` branch (see §1). The bridge works, the handler doesn't.

---

## Prioritised Fix Roadmap

| Priority | Issue | Effort | Impact |
|----------|-------|--------|--------|
| P0 | BUG-01: `config` undefined crash | 1 line | OAuth totally broken |
| P0 | BUG-02: OAuth scheme mismatch | 1 line + env var | OAuth totally broken |
| P0 | BUG-03: 4xx errors invisible | ~15 lines | UX broken on expired sessions |
| P1 | SEC-01: Token in postMessage | Medium refactor | Auth token exposure |
| P1 | Deployment: Missing plugins in app.json | 3 lines | EAS Build fails |
| P1 | Deployment: Missing Android permission | 2 lines | ImagePicker crash on Android 13 |
| P1 | Deployment: Missing iOS bundleIdentifier | 1 line | App Store submission blocked |
| P2 | SEC-02: Remove `NSAllowsArbitraryLoadsInWebContent` | 1 line | App Store ATS review |
| P2 | BUG-04: Stale closure race | Medium | Loading desync edge case |
| P2 | Native: Handle `MODAL_OPENED` in NativeService | ~10 lines | Android back button UX |
| P3 | Perf: Replace timeout splash with direct hideSplash | ~5 lines | Minor UX |
| P3 | Perf: Replace `setState` canGoBack with ref | ~3 lines | Re-render elimination |
| P3 | SEC-03: Strip `keyhome.test` from ATS | 1 line | Production hardening |

---

*Audit réalisé par Antigravity · 2026-02-23*
