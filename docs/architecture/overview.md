# KeyHome — Architecture Overview

> **Last updated:** April 2026
> **Status:** Production-ready

---

## System Topology

```
┌─────────────────────────────────────────────────────────────────────┐
│                          PUBLIC INTERNET                            │
│                                                                     │
│  ┌─────────────────┐  ┌──────────────────┐  ┌───────────────────┐  │
│  │  Next.js 16 PWA │  │ Filament Admin   │  │ Filament Agency   │  │
│  │ app.keyhome.app │  │ admin.keyhome.app│  │ agency.keyhome.app│  │
│  └────────┬────────┘  └────────┬─────────┘  └──────────┬────────┘  │
└───────────┼────────────────────┼───────────────────────┼───────────┘
            │                    │                         │
            ▼                    ▼                         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    Traefik Reverse Proxy (HTTPS)                    │
│                    Let's Encrypt TLS termination                    │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    Laravel 12 Application (PHP-FPM)                 │
│                                                                     │
│  REST API /api/v1/    │  Filament panels  │  Web routes             │
│  66+ endpoints        │  /admin, /agency  │  /tour-proxy, /sso      │
│  Sanctum + Clerk JWT  │  Session + MFA    │  /pwa-manifest          │
└──────┬────────────────────────────────────────────────┬────────────┘
       │                                                │
       ├──────────────────────┐                         │
       ▼                      ▼                         ▼
┌──────────────┐   ┌───────────────────┐    ┌──────────────────────┐
│ PostgreSQL   │   │    MeiliSearch    │    │       Redis           │
│ + PostGIS   │   │  (full-text idx)  │    │  cache / session /    │
│ Primary DB   │   │  AI-synced index  │    │  queues / rate limit  │
└──────────────┘   └───────────────────┘    └──────────────────────┘
       │
       ├── Cloudflare R2 (media storage — avatars, ads, tours, leases)
       ├── Flutterwave API (XAF/XOF payments)
       ├── Groq / OpenAI / Gemini (AI search & description)
       ├── OpenRouteService (isochrones, directions)
       ├── Sentry (error tracking)
       └── Laravel Nightwatch (uptime monitoring)
```

---

## Application Layers

### 1. Monorepo Structure

```
ImmoApp-Backend/
├── app/                        ← Laravel application
│   ├── Http/Controllers/Api/V1/  ← REST API controllers (thin, final)
│   ├── Services/               ← Business logic (final readonly)
│   ├── Actions/                ← Single-purpose action classes
│   ├── DTOs/                   ← Immutable value objects
│   ├── Models/                 ← Eloquent (UUID, soft-delete, Scout)
│   ├── Filament/               ← Admin & Agency panels
│   ├── Policies/               ← Authorization (one per model)
│   ├── Events/ & Listeners/    ← Event-driven architecture
│   ├── Jobs/                   ← Async queue jobs
│   ├── Mail/ & Notifications/  ← 45+ mailables, push, WhatsApp
│   └── Console/Commands/       ← 25+ Artisan commands
├── keyhome-frontend-next/      ← Next.js 16 web app (co-located)
├── mobile/                     ← React Native shells (agency, bailleur)
├── routes/api/                 ← Versioned API routes
├── database/migrations/        ← 126+ migrations
└── tests/                      ← 592+ Pest tests
```

### 2. Request Lifecycle

```
HTTP Request
    │
    ├── Middleware stack (SecurityHeaders, SanitizeInput, OptionalAuth, …)
    │
    ├── FormRequest validation (dedicated class per endpoint)
    │
    ├── Controller (final, no business logic)
    │       └── calls Action or Service
    │
    ├── Service / Action (business logic, throws domain exceptions)
    │       └── dispatches Events
    │
    ├── Events → Listeners (async via ShouldQueue where possible)
    │
    └── ApiResponse::success() / ApiResponse::error()
```

---

## Key Design Decisions

| Pattern | Where | Why |
|---------|-------|-----|
| `final readonly` Services | `app/Services/` | Immutable by default, forces DI, testable |
| `final` Controllers | `app/Http/Controllers/Api/V1/` | Thin layer — no inheritance abuse |
| FormRequest per endpoint | `app/Http/Requests/` | Validation isolated, reusable |
| DTOs for service returns | `app/DTOs/` | Decouples HTTP layer from business logic |
| Strategy pattern | `PaymentGatewayInterface` | Swap gateways without touching business logic |
| Event-driven | `app/Events/`, `app/Listeners/` | Decoupled side effects (email, boost, alerts) |
| UUID primary keys | all models | Safer IDs, no sequential enumeration |
| Soft deletes | key models | Audit trail, GDPR anonymization |
| PostGIS spatial | `Ad.location`, `User.location` | Native geo queries, nearby search |
| LandlordScope | `Ad` model | Multi-tenant agency scoping |

---

## Queue Architecture

| Queue | Worker | Timeout | Tries | Handles |
|-------|--------|---------|-------|---------|
| `critical` | `worker` | 90s | 3 | Auth, payments initiated |
| `payments` | `worker` | 90s | 3 | Payment verification, webhooks |
| `emails` | `worker` | 90s | 3 | All outbound mail |
| `default` | `worker` | 90s | 3 | Alerts, search matching, general |
| `tours` | `worker-tours` | 900s | 2 | 360° panorama processing |

---

## External Integrations

| Service | Purpose | Fallback |
|---------|---------|----------|
| **Flutterwave** | XAF/XOF payments | None (sole gateway) |
| **Clerk** | Frontend OAuth | Legacy email/password Sanctum |
| **Groq → OpenAI → Gemini** | AI natural search | Regex parser (`NaturalSearchRegexParser`) |
| **OpenRouteService** | Isochrones, directions | None (feature disabled) |
| **Cloudflare R2** | Media storage (prod) | Local disk (dev) |
| **Sentry** | Error tracking | Laravel logs |
| **MeiliSearch** | Full-text search | `null` driver in tests |
| **Mapbox** | Frontend maps | None |
| **Web Push (VAPID)** | Browser push notifications | Email fallback |

---

## Multi-Tenant Model

- **Agency panel** is multi-tenant: each agency sees only its own ads, payments, and team
- `LandlordScope` applied globally on `Ad` model within agency context
- Team invitations via `TeamInvitation` model
- Agency-scoped Sanctum tokens: prefix `owner_*`

## Authentication Model

See [Auth Flows](./auth-flows.md) for the complete breakdown.

---

## Scalability Considerations

- Redis capped at 384 MB (`allkeys-lru`) — prevents silent OOM
- `worker-tours` isolated with 1 GB limit (image processing)
- `chunkById(100/500)` in all bulk jobs — prevents memory exhaustion
- `lockForUpdate()` on payment verification — prevents double-spend
- MeiliSearch as separate service — search doesn't hit PostgreSQL under load
