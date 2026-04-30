# KeyHome — Real-Time Messaging System

**Stack:** Laravel 12 · Next.js 16 · PostgreSQL · Redis · Cloudflare R2 · Laravel Reverb · Firebase FCM  
**Security:** AES-256-CBC + HMAC-SHA256 · Sanctum Bearer auth · Private WebSocket channels  
**UI language:** French (`fr_FR`)

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Environment Variables — Backend (`.env`)](#environment-variables--backend-env)
3. [Environment Variables — Frontend (`keyhome-frontend-next/.env.local`)](#environment-variables--frontend-keyhome-frontend-nextenvlocal)
4. [Step-by-Step Configuration](#step-by-step-configuration)
   - [Step 1 — Chat Encryption Key](#step-1--chat-encryption-key)
   - [Step 2 — Laravel Reverb (WebSocket)](#step-2--laravel-reverb-websocket)
   - [Step 3 — Firebase FCM (Push Notifications)](#step-3--firebase-fcm-push-notifications)
   - [Step 4 — Queue Configuration](#step-4--queue-configuration)
   - [Step 5 — Database Migrations](#step-5--database-migrations)
5. [Production Deployment](#production-deployment)
6. [Feature Reference](#feature-reference)
7. [Security Notes](#security-notes)
8. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

```
Tenant (Next.js PWA)          Landlord (Next.js Owner Panel)
        │                               │
        └──────────── REST API ─────────┘
                          │
                   Laravel 12 API
                    /api/v1/conversations/*
                    /api/v1/messages/*
                    /api/v1/fcm/token
                          │
         ┌────────────────┼─────────────────┐
         │                │                 │
    PostgreSQL       Reverb WSS         Firebase FCM
    (encrypted       (real-time         (push when
     messages)        events)            offline)
         │
    Cloudflare R2
    (attachments,
     signed URLs)
```

**Business rule:** A conversation is created only after the tenant pays to unlock an Ad (`UnlockedAd`). One conversation per `(ad_id, tenant_id)` pair.

**Participants:** `tenant_id` ↔ `landlord_id` — always one-to-one, always tied to an Ad.

---

## Environment Variables — Backend (`.env`)

Add/update in `/Users/feze/Developer/Laravel/ImmoApp-Backend/.env`:

```dotenv
# ─── Broadcasting (must switch from log/sync to reverb) ──────────────
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=redis          # Required for push/email jobs

# ─── WebSocket (Reverb) ──────────────────────────────────────────────
REVERB_APP_ID=keyhome_chat
REVERB_APP_KEY=                 # Generate: see Step 2
REVERB_APP_SECRET=              # Generate: see Step 2
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https             # Use http for local dev

# ─── Chat Encryption ─────────────────────────────────────────────────
CHAT_ENCRYPTION_KEY=            # Generate: see Step 1

# ─── Firebase FCM (Backend service account) ──────────────────────────
FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
```

---

## Environment Variables — Frontend (`keyhome-frontend-next/.env.local`)

Add/update in `keyhome-frontend-next/.env.local`:

```dotenv
# ─── WebSocket (must match backend REVERB_APP_KEY) ───────────────────
NEXT_PUBLIC_REVERB_APP_KEY=     # Same value as backend REVERB_APP_KEY
NEXT_PUBLIC_REVERB_HOST=your-api-domain.com   # e.g. api.keyhome.app
NEXT_PUBLIC_REVERB_PORT=443     # 8080 for local dev
NEXT_PUBLIC_REVERB_SCHEME=https # http for local dev

# ─── Firebase (frontend web app config) ──────────────────────────────
NEXT_PUBLIC_FIREBASE_API_KEY=
NEXT_PUBLIC_FIREBASE_AUTH_DOMAIN=
NEXT_PUBLIC_FIREBASE_PROJECT_ID=
NEXT_PUBLIC_FIREBASE_STORAGE_BUCKET=
NEXT_PUBLIC_FIREBASE_MESSAGING_SENDER_ID=
NEXT_PUBLIC_FIREBASE_APP_ID=
NEXT_PUBLIC_FIREBASE_VAPID_KEY=
```

> **Vercel:** Add all `NEXT_PUBLIC_*` variables in Vercel Dashboard → Project → Settings → Environment Variables (Production + Preview).

---

## Step-by-Step Configuration

### Step 1 — Chat Encryption Key

All message bodies are encrypted with AES-256-CBC before storage. The key must be a 32-byte random hex string.

```bash
# Run in the backend directory
php -r "echo bin2hex(random_bytes(32));"
```

Copy the output into `.env`:

```dotenv
CHAT_ENCRYPTION_KEY=a3f8c2d1e4b5a6f7c8d9e0b1a2f3c4d5e6b7a8f9c0d1e2b3a4f5c6d7e8b9a0f1
```

> ⚠️ **Never change this key in production** — all existing messages will become unreadable.  
> ⚠️ Never commit this key to Git.  
> ✅ Store it in your secrets manager (GitLab CI/CD variables, Infisical, etc.).

---

### Step 2 — Laravel Reverb (WebSocket)

#### 2a. Generate Reverb credentials

```bash
# REVERB_APP_KEY  (16-byte = 32 hex chars)
php -r "echo bin2hex(random_bytes(16));"

# REVERB_APP_SECRET  (32-byte = 64 hex chars)
php -r "echo bin2hex(random_bytes(32));"
```

#### 2b. Update backend `.env`

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=keyhome_chat
REVERB_APP_KEY=<output of first command>
REVERB_APP_SECRET=<output of second command>
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https
```

#### 2c. Update frontend `.env.local`

```dotenv
# NEXT_PUBLIC_REVERB_APP_KEY must be identical to backend REVERB_APP_KEY
NEXT_PUBLIC_REVERB_APP_KEY=<same value as REVERB_APP_KEY>
NEXT_PUBLIC_REVERB_HOST=api.keyhome.app     # your production API domain
NEXT_PUBLIC_REVERB_PORT=443                 # 8080 for local dev
NEXT_PUBLIC_REVERB_SCHEME=https             # http for local dev
```

#### 2d. Start Reverb locally

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080 --debug
```

#### 2e. Nginx proxy (production)

Add to your Nginx server block:

```nginx
location /app/ {
    proxy_pass             http://localhost:8080;
    proxy_http_version     1.1;
    proxy_set_header       Upgrade $http_upgrade;
    proxy_set_header       Connection "Upgrade";
    proxy_set_header       Host $host;
    proxy_read_timeout     86400;
}
```

#### 2f. Supervisor (production — keep Reverb alive)

```ini
[program:reverb]
command=php /var/www/keyhome/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/reverb.log
```

---

### Step 3 — Firebase FCM (Push Notifications)

Firebase is used for push notifications when the recipient is offline or the browser is in the background.

#### 3a. Create a Firebase project

1. Go to [console.firebase.google.com](https://console.firebase.google.com)
2. Create a project (or use an existing one)
3. Enable **Cloud Messaging** in the project

#### 3b. Backend service account (PHP)

1. Firebase Console → ⚙️ **Project Settings** → **Service accounts**
2. Click **"Generate new private key"** → download `firebase-credentials.json`
3. Place the file at:
   ```
   storage/app/firebase-credentials.json
   ```
4. Confirm it is in `.gitignore` (already added):
   ```
   /storage/app/firebase-credentials.json
   ```
5. Set in `.env`:
   ```dotenv
   FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
   ```

#### 3c. Frontend web app config

1. Firebase Console → ⚙️ **Project Settings** → **General**
2. Scroll to **"Your apps"** → **Add app** → Web (`</>`)
3. Register the app, then copy the config into `keyhome-frontend-next/.env.local`:

```dotenv
NEXT_PUBLIC_FIREBASE_API_KEY=AIzaSy...
NEXT_PUBLIC_FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
NEXT_PUBLIC_FIREBASE_PROJECT_ID=your-project
NEXT_PUBLIC_FIREBASE_STORAGE_BUCKET=your-project.firebasestorage.app
NEXT_PUBLIC_FIREBASE_MESSAGING_SENDER_ID=123456789
NEXT_PUBLIC_FIREBASE_APP_ID=1:123456789:web:abc123
```

> Do **not** copy Firebase's suggested `initializeApp` code into a JS file — the project already handles this in `src/hooks/useFcmToken.ts` using the env vars above.

#### 3d. VAPID key (Web Push)

1. Firebase Console → ⚙️ **Project Settings** → **Cloud Messaging**
2. Scroll to **"Web Push certificates"**
3. Click **"Generate key pair"**
4. Copy the key pair string into:

```dotenv
NEXT_PUBLIC_FIREBASE_VAPID_KEY=BNJ...
```

#### 3e. Verify service worker

The file `keyhome-frontend-next/public/firebase-messaging-sw.js` is already present and handles background push notifications. It reads the Firebase config via URL params passed by `useFcmToken.ts` — no changes needed.

---

### Step 4 — Queue Configuration

Push notification and email jobs are dispatched to the queue. In production, Redis + Horizon must be running.

```dotenv
# .env (backend)
QUEUE_CONNECTION=redis
```

```bash
# Start Horizon (manages all queues)
php artisan horizon

# Or start a specific worker manually
php artisan queue:work redis --queue=notifications,emails,default
```

> For local development, you can temporarily use `QUEUE_CONNECTION=sync` to process jobs synchronously (no Horizon needed), but push notifications won't work without Redis.

---

### Step 5 — Database Migrations

Run the chat migrations (in order):

```bash
php artisan migrate
```

This creates:

| Table | Purpose |
|---|---|
| `conversations` | One conversation per `(ad_id, tenant_id)` pair |
| `messages` | Encrypted message bodies + attachments jsonb |
| `fcm_tokens` | Firebase device tokens per user |

To add to an existing installation (if the app was already migrated):

```bash
php artisan migrate --path=database/migrations/2026_04_19_105924_create_conversations_table.php
php artisan migrate --path=database/migrations/2026_04_19_105924_create_messages_table.php
php artisan migrate --path=database/migrations/2026_04_19_105924_create_fcm_tokens_table.php
php artisan migrate --path=database/migrations/2026_04_19_110000_add_last_message_id_to_conversations.php
```

---

## Production Deployment

### Backend checklist

```bash
# 1. Run migrations
php artisan migrate --force

# 2. Start Reverb (via Supervisor — see Step 2f)
php artisan reverb:start --host=0.0.0.0 --port=8080

# 3. Start Horizon (queue workers)
php artisan horizon

# 4. Verify all env vars are set
php artisan about | grep -i "reverb\|broadcast\|queue"
```

### Frontend checklist

```bash
# All NEXT_PUBLIC_* vars must be set BEFORE build
pnpm build

# Verify service worker is accessible
curl https://your-frontend.vercel.app/firebase-messaging-sw.js
```

### Environment variables summary

| Variable | Location | Source |
|---|---|---|
| `CHAT_ENCRYPTION_KEY` | Backend `.env` | `php -r "echo bin2hex(random_bytes(32));"` |
| `REVERB_APP_ID` | Backend `.env` | Any string — keep `keyhome_chat` |
| `REVERB_APP_KEY` | Backend `.env` | `php -r "echo bin2hex(random_bytes(16));"` |
| `REVERB_APP_SECRET` | Backend `.env` | `php -r "echo bin2hex(random_bytes(32));"` |
| `FIREBASE_CREDENTIALS` | Backend `.env` | Firebase Console → Service accounts |
| `NEXT_PUBLIC_REVERB_APP_KEY` | Frontend `.env.local` | Same as `REVERB_APP_KEY` |
| `NEXT_PUBLIC_REVERB_HOST` | Frontend `.env.local` | Your API domain |
| `NEXT_PUBLIC_FIREBASE_API_KEY` | Frontend `.env.local` | Firebase Console → Web app |
| `NEXT_PUBLIC_FIREBASE_AUTH_DOMAIN` | Frontend `.env.local` | Firebase Console → Web app |
| `NEXT_PUBLIC_FIREBASE_PROJECT_ID` | Frontend `.env.local` | Firebase Console → Web app |
| `NEXT_PUBLIC_FIREBASE_STORAGE_BUCKET` | Frontend `.env.local` | Firebase Console → Web app |
| `NEXT_PUBLIC_FIREBASE_MESSAGING_SENDER_ID` | Frontend `.env.local` | Firebase Console → Web app |
| `NEXT_PUBLIC_FIREBASE_APP_ID` | Frontend `.env.local` | Firebase Console → Web app |
| `NEXT_PUBLIC_FIREBASE_VAPID_KEY` | Frontend `.env.local` | Firebase Console → Cloud Messaging → Web Push |

---

## Feature Reference

### What's implemented

| Feature | Backend | Frontend |
|---|---|---|
| One-to-one conversations (tied to Ad) | ✅ `ConversationService` | ✅ `useConversations` |
| Real-time delivery (WebSocket) | ✅ Reverb + `MessageSent` event | ✅ `useChat` + Echo |
| AES-256-CBC message encryption | ✅ `EncryptionService` | ✅ decrypted at API layer |
| Cursor-paginated message history | ✅ `MessageService::getHistory()` | ✅ `loadMore` in `useChat` |
| Optimistic UI updates | — | ✅ message appears before server confirms |
| Typing indicator | ✅ `UserTyping` event | ✅ `useTypingIndicator` |
| Read receipts (✓ / ✓✓ / ✓✓🔵) | ✅ `MessageStatus` enum | ✅ `MessageBubble` |
| Online/offline presence | ✅ `UserPresence` (presence channel) | ✅ `usePresence` |
| Message reply (quote) | ✅ `reply_to_id` FK | ✅ `ReplyPreview` + `MessageInput` |
| Message soft-delete (24h window) | ✅ `MessageService::delete()` | ✅ long-press / hover action |
| Image upload + lightbox | ✅ `AttachmentService` (R2) | ✅ `AttachmentPreview` modal |
| File/PDF sharing | ✅ `AttachmentService` | ✅ download button |
| Signed URLs (24h expiry) | ✅ Cloudflare R2 | ✅ never exposed raw path |
| FCM push (background) | ✅ `SendChatPushNotificationJob` | ✅ `firebase-messaging-sw.js` |
| In-app toast (foreground) | — | ✅ `ChatNotificationListener` |
| Email notification (5min delay) | ✅ `SendOfflineEmailNotificationJob` | — |
| Unread badge on nav icon | ✅ `/api/v1/conversations/unread-count` | ✅ `ChatBadgeIcon` |
| Conversation list with search | — | ✅ client-side filter |
| Archive conversation | ✅ `ConversationService::archive()` | ✅ action in UI |
| WebSocket reconnect indicator | — | ✅ amber banner in `ChatWindow` |
| PWA standalone height fix | — | ✅ `ChatPageWrapper` |
| iOS safe-area-inset-bottom | — | ✅ `MessageInput` |
| Two-column Messenger layout | — | ✅ `/messages` + `/messages/[uuid]` |
| Rate limiting on all write endpoints | ✅ 60 msg/min, 10 upload/min | — |
| IDOR prevention (404 not 403) | ✅ | — |

### API routes

```
GET    /api/v1/conversations                    List conversations (paginated)
POST   /api/v1/conversations                    Find or create (requires UnlockedAd)
GET    /api/v1/conversations/{uuid}             Single conversation detail
GET    /api/v1/conversations/{uuid}/messages    Message history (cursor paginated)
POST   /api/v1/conversations/{uuid}/messages    Send a message
POST   /api/v1/conversations/{uuid}/attachments Upload file to R2
PATCH  /api/v1/conversations/{uuid}/read        Mark as read
POST   /api/v1/conversations/{uuid}/typing      Typing event (ephemeral)
PATCH  /api/v1/conversations/{uuid}/archive     Archive conversation
GET    /api/v1/conversations/unread-count       Global unread count
DELETE /api/v1/messages/{uuid}                  Soft-delete message (sender only, 24h)
POST   /api/v1/fcm/token                        Register FCM device token
```

### WebSocket channels

```
private conversation.{uuid}   → MessageSent, MessageRead, MessageDeleted, UserTyping
presence online-users          → UserPresence (online/offline status)
```

---

## Security Notes

- **Encryption key rotation** — not supported without re-encrypting all existing messages. Treat `CHAT_ENCRYPTION_KEY` as permanent once set in production.
- **Signed URLs** — attachment URLs expire after 24 hours. The `CleanExpiredSignedUrlsJob` (scheduled daily) refreshes them.
- **Channel auth** — every WebSocket subscription goes through `/broadcasting/auth` (Sanctum Bearer token). Only the two participants of a conversation can subscribe to its channel.
- **IDOR** — all endpoints return 404 (not 403) when a user tries to access another user's conversation.
- **Body IV** — the `body_iv` column is in `$hidden` on the `Message` model and is never returned by any API endpoint.
- **Rate limits** — send message: 60/min · upload: 10/min · typing: 30/min.

---

## Troubleshooting

### Messages not delivered in real-time

1. Confirm Reverb is running: `php artisan reverb:start`
2. Confirm `BROADCAST_CONNECTION=reverb` in `.env` (not `log`)
3. Confirm `NEXT_PUBLIC_REVERB_APP_KEY` matches `REVERB_APP_KEY`
4. Check browser console for WebSocket errors
5. Amber reconnect banner visible? → Reverb is unreachable from the frontend

### Push notifications not received

1. Confirm `QUEUE_CONNECTION=redis` and Horizon is running
2. Confirm `FIREBASE_CREDENTIALS` path is correct and file exists
3. Confirm all `NEXT_PUBLIC_FIREBASE_*` vars are set
4. Confirm `NEXT_PUBLIC_FIREBASE_VAPID_KEY` matches the key in Firebase Console
5. Check the browser has granted notification permission
6. Check `failed_jobs` table for `SendChatPushNotificationJob` failures

### "Conversation introuvable" on `/messages/[uuid]`

- The tenant has not unlocked the Ad yet (`UnlockedAd` record missing)
- Or the UUID does not belong to the authenticated user (IDOR protection)

### Encryption errors on boot

```
RuntimeException: CHAT_ENCRYPTION_KEY is not set or invalid
```

→ Run `php -r "echo bin2hex(random_bytes(32));"` and set the result in `.env`

### Migrations fail on PostgreSQL

The `messages` table uses a self-referential FK (`reply_to_id → messages.id`). This is split into two separate `Schema::table()` calls to avoid PostgreSQL circular reference errors. Run `php artisan migrate:fresh` if you hit FK issues during initial setup.
