# OAuth Authentication Integration Guide

## Overview

KeyHome supports OAuth authentication via:
- **Google** - Web, Mobile (Android/iOS), Filament Panels
- **Facebook** - Web, Mobile (iOS/Android)  
- **Apple** - Mobile (iOS), Web

## API Endpoints

### 1. Authenticate with OAuth Token (Mobile/SPA)

**POST** `/api/v1/auth/oauth/{provider}`

Mobile apps get the OAuth token from their SDK, then send it here.

```bash
curl -X POST https://api.keyhome.cm/api/v1/auth/oauth/google \
  -H "Content-Type: application/json" \
  -d '{
    "token": "ya29.a0AfH6SMB...",
    "role": "customer"  // optional: "customer" or "agent"
  }'
```

**Response:**
```json
{
  "message": "Connexion réussie",
  "user": { ... },
  "token": "1|abc123...",
  "is_new_user": false
}
```

### 2. Web OAuth Redirect Flow

**GET** `/api/v1/auth/oauth/{provider}/redirect`

Returns the OAuth provider authorization URL.

```bash
curl "https://api.keyhome.cm/api/v1/auth/oauth/google/redirect?redirect_uri=https://keyhome.cm/auth/callback"
```

**Response:**
```json
{
  "redirect_url": "https://accounts.google.com/o/oauth2/v2/auth?..."
}
```

### 3. Link Provider to Existing Account

**POST** `/api/v1/auth/oauth/{provider}/link`

Requires authentication.

```bash
curl -X POST https://api.keyhome.cm/api/v1/auth/oauth/facebook/link \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"token": "facebook-access-token"}'
```

### 4. Unlink Provider

**DELETE** `/api/v1/auth/oauth/{provider}/unlink`

```bash
curl -X DELETE https://api.keyhome.cm/api/v1/auth/oauth/google/unlink \
  -H "Authorization: Bearer {token}"
```

## Environment Variables

Add these to your `.env`:

```env
# Google OAuth
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=https://api.keyhome.cm/api/v1/auth/oauth/google/callback

# Facebook OAuth
FACEBOOK_CLIENT_ID=your-facebook-app-id
FACEBOOK_CLIENT_SECRET=your-facebook-app-secret
FACEBOOK_REDIRECT_URI=https://api.keyhome.cm/api/v1/auth/oauth/facebook/callback

# Apple OAuth
APPLE_CLIENT_ID=your-apple-service-id
APPLE_CLIENT_SECRET=your-apple-client-secret
APPLE_REDIRECT_URI=https://api.keyhome.cm/api/v1/auth/oauth/apple/callback
```

## Provider Setup

### Google

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable **Google+ API** and **People API**
4. Go to **Credentials** → **Create Credentials** → **OAuth 2.0 Client ID**
5. Configure:
   - **Application type**: Web application
   - **Authorized redirect URIs**: 
     - `https://api.keyhome.cm/api/v1/auth/oauth/google/callback`
     - `https://localhost:8000/api/v1/auth/oauth/google/callback` (dev)
6. Copy Client ID and Client Secret

### Facebook

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create a new app → **Consumer** type
3. Add **Facebook Login** product
4. Configure:
   - **Valid OAuth Redirect URIs**:
     - `https://api.keyhome.cm/api/v1/auth/oauth/facebook/callback`
5. Go to **Settings** → **Basic** for App ID and Secret

### Apple

1. Go to [Apple Developer](https://developer.apple.com/)
2. Create an **App ID** with Sign In with Apple capability
3. Create a **Services ID** (this is your client_id)
4. Configure:
   - **Domains**: `api.keyhome.cm`
   - **Return URLs**: `https://api.keyhome.cm/api/v1/auth/oauth/apple/callback`
5. Create a **Key** for Sign In with Apple
6. Generate client secret (JWT) - see [Apple Docs](https://developer.apple.com/documentation/sign_in_with_apple/generate_and_validate_tokens)

## Mobile Integration

### React Native (Expo)

```typescript
// Install
// npx expo install expo-auth-session expo-web-browser expo-crypto

import * as Google from 'expo-auth-session/providers/google';
import * as Facebook from 'expo-auth-session/providers/facebook';
import * as AppleAuthentication from 'expo-apple-authentication';

// Google
const [request, response, promptAsync] = Google.useAuthRequest({
  androidClientId: 'YOUR_ANDROID_CLIENT_ID',
  iosClientId: 'YOUR_IOS_CLIENT_ID',
  webClientId: 'YOUR_WEB_CLIENT_ID',
});

const handleGoogleLogin = async () => {
  const result = await promptAsync();
  if (result.type === 'success') {
    const { accessToken } = result.authentication;
    
    const response = await fetch('https://api.keyhome.cm/api/v1/auth/oauth/google', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: accessToken }),
    });
    
    const data = await response.json();
    // Store data.token for authenticated requests
  }
};

// Apple (iOS only)
const handleAppleLogin = async () => {
  const credential = await AppleAuthentication.signInAsync({
    requestedScopes: [
      AppleAuthentication.AppleAuthenticationScope.FULL_NAME,
      AppleAuthentication.AppleAuthenticationScope.EMAIL,
    ],
  });
  
  const response = await fetch('https://api.keyhome.cm/api/v1/auth/oauth/apple', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      token: credential.authorizationCode,
      id_token: credential.identityToken,
    }),
  });
  
  const data = await response.json();
};
```

### Next.js Frontend

```typescript
// Using next-auth or custom implementation

// pages/api/auth/[...nextauth].ts (with next-auth)
import NextAuth from 'next-auth';
import GoogleProvider from 'next-auth/providers/google';
import FacebookProvider from 'next-auth/providers/facebook';
import AppleProvider from 'next-auth/providers/apple';

export default NextAuth({
  providers: [
    GoogleProvider({
      clientId: process.env.GOOGLE_CLIENT_ID!,
      clientSecret: process.env.GOOGLE_CLIENT_SECRET!,
    }),
    FacebookProvider({
      clientId: process.env.FACEBOOK_CLIENT_ID!,
      clientSecret: process.env.FACEBOOK_CLIENT_SECRET!,
    }),
    AppleProvider({
      clientId: process.env.APPLE_CLIENT_ID!,
      clientSecret: process.env.APPLE_CLIENT_SECRET!,
    }),
  ],
  callbacks: {
    async signIn({ user, account }) {
      // Send token to Laravel backend
      const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/api/v1/auth/oauth/${account.provider}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: account.access_token }),
      });
      
      const data = await res.json();
      // Store Laravel token
      return true;
    },
  },
});
```

## Filament Panel Integration

OAuth for Filament panels uses the `filament-socialite` package. The panels are configured to use Google OAuth.

### Configured Panels

| Panel | Path | OAuth Registration | Provider |
|-------|------|-------------------|----------|
| Admin | `/admin` | Disabled | Google |
| Agency | `/agency` | Enabled | Google |
| Bailleur | `/bailleur` | Enabled | Google |

### Configuration

```php
// Example: app/Providers/Filament/AgencyPanelProvider.php
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Support\Colors\Color;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentSocialitePlugin::make()
                ->providers([
                    Provider::make('google')
                        ->label('Google')
                        ->icon('fab-google')
                        ->color(Color::Rose)
                        ->outlined(false)
                        ->stateless(false),
                ])
                ->registration(true)
                ->rememberLogin(true)
                ->showDivider(true),
        ]);
}
```

### Database Migration

The package uses a separate `socialite_users` table:

```php
Schema::create('socialite_users', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->string('provider');
    $table->string('provider_id');
    $table->timestamps();
    $table->unique(['provider', 'provider_id']);
});
```

## Mobile App Integration (React Native)

The mobile apps (Agency & Bailleur) use WebView to display Filament panels but support native OAuth for a better UX.

### Architecture

```
┌─────────────────┐      ┌─────────────────┐      ┌─────────────────┐
│   React Native  │──────│   OAuthService  │──────│  Laravel API    │
│   WebView       │      │   (native SDK)  │      │  /api/v1/auth   │
└─────────────────┘      └─────────────────┘      └─────────────────┘
        │                         │
        │   postMessage()         │   expo-auth-session
        │◄────────────────────────│
```

### OAuthService.js

Location: `mobile/{agency,bailleur}/services/OAuthService.js`

```javascript
// Trigger OAuth from WebView
window.ReactNativeWebView.postMessage(JSON.stringify({
  type: 'OAUTH_SIGN_IN',
  data: { provider: 'google', panelType: 'agency' }
}));

// Listen for results
window.addEventListener('message', (event) => {
  const msg = JSON.parse(event.data);
  if (msg.type === 'OAUTH_SUCCESS') {
    // User authenticated - reload or redirect
    window.location.href = '/agency';
  }
});
```

### Environment Variables (Mobile)

```env
# .env in mobile/agency or mobile/bailleur
EXPO_PUBLIC_BASE_URL=https://api.keyhome.neocraft.dev
EXPO_PUBLIC_GOOGLE_CLIENT_ID_WEB=your-google-web-client-id
EXPO_PUBLIC_GOOGLE_CLIENT_ID_ANDROID=your-google-android-client-id
EXPO_PUBLIC_GOOGLE_CLIENT_ID_IOS=your-google-ios-client-id
```

### Required Expo Packages

```bash
npx expo install expo-auth-session expo-web-browser expo-secure-store expo-crypto
```

### app.json OAuth Scheme

```json
{
  "expo": {
    "scheme": "keyhome-agency",
    "ios": {
      "bundleIdentifier": "com.keyhome.agency"
    },
    "android": {
      "package": "com.keyhome.agency"
    }
  }
}
```

## Security Considerations

1. **Token Validation**: All OAuth tokens are validated with the respective provider
2. **Email Verification**: OAuth users have pre-verified emails
3. **Rate Limiting**: 10 requests/minute per IP
4. **HTTPS Only**: OAuth endpoints require HTTPS in production
5. **State Parameter**: CSRF protection for web redirect flow
6. **SecureStore**: Mobile apps store tokens securely using expo-secure-store

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Invalid token" | Check token hasn't expired, use fresh token from SDK |
| "Provider not supported" | Use lowercase: google, facebook, apple |
| "Email already exists" | User exists - OAuth will link to existing account |
| "Cannot unlink" | Set password first or link another provider |
| "Socialite UUID error" | Migration must use `foreignUuid()` not `foreignId()` |
| Mobile OAuth popup closes | Check redirect URI scheme matches app.json |

## Database Schema

```sql
-- OAuth fields added to users table
ALTER TABLE users ADD COLUMN google_id VARCHAR(255) UNIQUE;
ALTER TABLE users ADD COLUMN facebook_id VARCHAR(255) UNIQUE;
ALTER TABLE users ADD COLUMN apple_id VARCHAR(255) UNIQUE;
ALTER TABLE users ADD COLUMN oauth_provider VARCHAR(50);
ALTER TABLE users ADD COLUMN oauth_avatar VARCHAR(500);
ALTER TABLE users MODIFY password VARCHAR(255) NULL;

-- Filament Socialite table
CREATE TABLE socialite_users (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    provider VARCHAR(255) NOT NULL,
    provider_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(provider, provider_id)
);
```
