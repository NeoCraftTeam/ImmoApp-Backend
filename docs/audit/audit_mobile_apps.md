# Audit Report — React Native Mobile Apps (`mobile/agency` + `mobile/bailleur`)

## Application Overview

Both mobile apps are **Expo WebView wrappers** that load Filament-based admin panels in a WebView. They share an identical architecture and `NativeService` class but are maintained as separate copies.

| Attribute | Agency | Bailleur (Owner) |
|---|---|---|
| **Package** | `cm.neocraft.keyhome.agency` | `cm.neocraft.keyhome.bailleur` |
| **Base URL** | `keyhomeback.neocraft.dev/agency` | `keyhomeback.neocraft.dev/bailleur` |
| **Theme** | Blue (`#2563eb`) | Green (`#10b981`) |
| **Framework** | Expo 54, React Native 0.81.5 | Expo 54, React Native 0.81.5 |
| **Key Deps** | WebView 13.15, expo-camera, expo-location, expo-notifications | Same |

---

## 🔴 Critical Findings

### MC-1. iOS App Transport Security Disabled — All HTTP Traffic Allowed

| Attribute | Value |
|---|---|
| **Files** | [agency/app.json](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/agency/app.json#L18-L20), [bailleur/app.json](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/app.json#L18-L20) |
| **Severity** | 🔴 Critical |
| **Affects** | Both apps |

**Evidence:**
```json
"NSAppTransportSecurity": {
  "NSAllowsArbitraryLoads": true
}
```

This **disables iOS App Transport Security** — the app allows plaintext HTTP connections to **any** host. This means:
- All WebView traffic can be intercepted via MITM attacks
- Session tokens and credentials sent over HTTP are fully exposed
- **Apple will reject this in App Store review** without a valid justification

**Remediation:**
```diff
-"NSAllowsArbitraryLoads": true
+"NSAllowsArbitraryLoads": false,
+"NSExceptionDomains": {
+  "keyhomeback.neocraft.dev": {
+    "NSExceptionAllowsInsecureHTTPLoads": false,
+    "NSExceptionRequiresForwardSecrecy": true
+  }
+}
```

---

### MC-2. WebView `originWhitelist` Allows `http://localhost*`

| Attribute | Value |
|---|---|
| **Files** | [agency/App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/agency/App.js#L115), [bailleur/App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/App.js#L115) |
| **Severity** | 🔴 Critical |
| **Affects** | Both apps |

**Evidence:**
```javascript
originWhitelist={['http://localhost*', 'https://*.keyhome.neocraft.dev', 'https://api.keyhome.neocraft.dev']}
```

The wildcard `http://localhost*` in production allows:
- Any local service or malicious redirect to load in the WebView
- Potential for WebView-based phishing on jailbroken/rooted devices
- `NativeService` origin check also allows `http://localhost` — native APIs (camera, location, notifications) can be invoked by local HTTP content

**Remediation:**
```diff
-originWhitelist={['http://localhost*', 'https://*.keyhome.neocraft.dev', 'https://api.keyhome.neocraft.dev']}
+originWhitelist={['https://*.keyhome.neocraft.dev']}
```
And update `NativeService` similarly:
```diff
-const allowedOrigins = ['http://localhost', ...];
+const allowedOrigins = ['https://keyhomeback.neocraft.dev', 'https://api.keyhome.neocraft.dev'];
```

---

## 🟠 High Findings

### MH-1. `.env` Files Committed with Production URLs

| Attribute | Value |
|---|---|
| **Files** | [agency/.env](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/agency/.env), [bailleur/.env](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/.env) |
| **Severity** | 🟠 High |

**Evidence:**
```
# agency/.env
EXPO_PUBLIC_BASE_URL=https://keyhomeback.neocraft.dev/agency

# bailleur/.env
EXPO_PUBLIC_BASE_URL=https://keyhomeback.neocraft.dev/bailleur
```

`.env` files with production URLs are committed to the repository. While `EXPO_PUBLIC_*` values are client-visible by design, committing `.env` files:
- Creates environment confusion (dev uses production URLs)
- Violates 12-factor app principles
- `.gitignore` includes `.env` but the files are already tracked

> [!WARNING]
> The base URL is `keyhomeback.neocraft.dev` but the CORS config and other references use `api.keyhome.neocraft.dev`. This URL mismatch could cause production issues.

**Remediation:**
- Remove `.env` files from git tracking: `git rm --cached mobile/agency/.env mobile/bailleur/.env`
- Create `.env.example` files with placeholder URLs
- Use EAS secrets or build profiles for environment-specific URLs

---

### MH-2. No SSL Certificate Pinning

| Attribute | Value |
|---|---|
| **Severity** | 🟠 High |
| **Affects** | Both apps |

**Evidence:** Neither app implements SSL pinning. Combined with MC-1 (ATS disabled), a MITM attacker with a proxy certificate can intercept all WebView traffic, including:
- Filament session cookies
- Admin credentials entered in the WebView login form
- All data displayed in the WebView

**Remediation:** Use `react-native-ssl-pinning` or configure pinning in `app.json`:
```json
"ios": {
  "infoPlist": {
    "NSAppTransportSecurity": {
      "NSPinnedDomains": {
        "keyhomeback.neocraft.dev": {
          "NSPinnedLeafIdentities": [{ "SPKI-SHA256-BASE64": "<hash>" }]
        }
      }
    }
  }
}
```

---

### MH-3. Base64 Images via `postMessage` — Memory Pressure

| Attribute | Value |
|---|---|
| **File** | [NativeService.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/agency/services/NativeService.js#L117-L126) |
| **Severity** | 🟠 High |

**Evidence:**
```javascript
const result = await ImagePicker.launchImageLibraryAsync({
  base64: true, // Full base64 encoding of the image
});
this.sendToWebView('IMAGE_SELECTED', {
  uri: result.assets[0].uri,
  base64: result.assets[0].base64,  // Can be several MB
});
```

Full-resolution images are base64-encoded and sent via `postMessage`. A 5MB JPEG becomes ~6.7MB base64 string. On devices with limited memory, sending multiple images this way can cause:
- App crashes (OOM)
- WebView freezes
- Bridge serialization delays

**Remediation:**
- Send only the local `uri` to the WebView
- Use a native file upload approach (e.g., `expo-file-system` + `fetch()`) to upload directly to the API from native code
- Or resize images before encoding (e.g., `quality: 0.3`, max width: 1024)

---

### MH-4. Over-Broad Android Permissions

| Attribute | Value |
|---|---|
| **Files** | [agency/app.json](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/agency/app.json#L33-L39), [bailleur/app.json](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/app.json#L33-L39) |
| **Severity** | 🟠 High |

**Evidence:**
```json
"permissions": [
  "CAMERA",
  "READ_EXTERNAL_STORAGE",
  "WRITE_EXTERNAL_STORAGE",
  "ACCESS_FINE_LOCATION",
  "ACCESS_COARSE_LOCATION"
]
```

- `WRITE_EXTERNAL_STORAGE` is **unnecessary on Android 10+** (API 29+, uses scoped storage)
- `READ_EXTERNAL_STORAGE` is **unnecessary on Android 13+** (API 33+, uses photo picker)
- Google Play will flag excessive permissions and may reject the app

**Remediation:**
```diff
 "permissions": [
   "CAMERA",
-  "READ_EXTERNAL_STORAGE",
-  "WRITE_EXTERNAL_STORAGE",
   "ACCESS_FINE_LOCATION",
   "ACCESS_COARSE_LOCATION"
 ]
```
Expo's `expo-image-picker` handles storage access internally.

---

## 🟡 Medium Findings

| ID | Finding | File | Impact |
|---|---|---|---|
| MM-1 | `NativeService.js` is **100% duplicated** between `agency` and `bailleur` | [agency/NativeService.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/agency/services/NativeService.js), [bailleur/NativeService.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/services/NativeService.js) | Bug fixes must be applied twice; drift risk |
| MM-2 | No deep link validation — `?app_mode=native` appended to URL could be spoofed | Both `App.js` | Phishing via crafted URI |
| MM-3 | WebView error handler exposes `nativeEvent.description` to users | Both [App.js](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/agency/App.js#L129) | Information disclosure |
| MM-4 | `injectedJavaScriptBeforeContentLoaded` sets `window.isNativeApp = true` — any page can detect this | Both `App.js` | Fingerprinting, targeted attacks |
| MM-5 | No offline support — blank screen when no network | Both apps | Poor UX in low-connectivity areas (Cameroon) |
| MM-6 | `App.js.backup` committed in bailleur directory | [bailleur/App.js.backup](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/bailleur/App.js.backup) | Code hygiene, potential secret leak |

---

## 🟢 Low Findings

| ID | Finding | File |
|---|---|---|
| ML-1 | App version hardcoded as `1.0.0` in both `app.json` and splash screen | Both `App.js` |
| ML-2 | No `expo-updates` configured — no OTA update capability | Both `app.json` |
| ML-3 | Agency `expo.name` is `"agency"` instead of a user-facing display name | [agency/app.json](file:///Users/feze/Developer/Laravel/ImmoApp-Backend/mobile/agency/app.json#L3) |
| ML-4 | Unused `Dimensions` import | Both `App.js` |
| ML-5 | `splashDuration` (2500ms) is defined but never used as a timer — splash hides based on WebView load | Both `App.js` |

---

## Remediation Plan

### 🚨 Quick Wins (< 1 hour each)

| # | Finding | Action |
|---|---|---|
| 1 | MC-1 | Set `NSAllowsArbitraryLoads: false` with domain exceptions |
| 2 | MC-2 | Remove `http://localhost*` from `originWhitelist` in both apps |
| 3 | MH-4 | Remove `READ/WRITE_EXTERNAL_STORAGE` permissions |
| 4 | MH-1 | Remove `.env` from git, create `.env.example`, use EAS secrets |
| 5 | MM-6 | Delete `App.js.backup` |
| 6 | MM-3 | Replace `nativeEvent.description` with generic error message |

### 📋 Short-Term (1–2 weeks)

| # | Finding | Action |
|---|---|---|
| 7 | MM-1 | Extract shared code into a `mobile/shared` package or monorepo setup (npm workspace) |
| 8 | MH-3 | Replace base64 image transfer with URI-based native upload |
| 9 | MC-2 | Update `NativeService` allowed origins to match production only |
| 10 | MM-4 | Use a unique app identifier token instead of `window.isNativeApp = true` |

### 🔧 Mid-Term (1–2 months)

| # | Finding | Action |
|---|---|---|
| 11 | MH-2 | Implement SSL certificate pinning |
| 12 | MM-5 | Add offline fallback UI with cached content |
| 13 | ML-2 | Configure `expo-updates` for OTA updates |
| 14 | — | Set up EAS Build for CI/CD pipelines |

### 🏗️ Long-Term (3–6 months)

| # | Action |
|---|---|
| 15 | Evaluate migrating from WebView wrapper to native screens for core functionality |
| 16 | Add biometric authentication (Face ID / fingerprint) for app unlock |
| 17 | Implement crash reporting (Sentry RN SDK) |
| 18 | Add accessibility compliance (VoiceOver, TalkBack support) |
