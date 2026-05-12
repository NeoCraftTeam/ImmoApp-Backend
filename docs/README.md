# KeyHome — Documentation Hub

> **Platform:** Multi-tenant real-estate SaaS for francophone West/Central Africa (XAF/XOF)
> **Stack:** Laravel 12 · Next.js 16 · Filament 4 · PostgreSQL/PostGIS · MeiliSearch · Flutterwave
> **Last updated:** April 2026

---

## 🗂️ Documentation Map

```
docs/
├── README.md                     ← You are here — master navigation index
├── architecture/
│   ├── overview.md               ← System architecture & component diagram
│   ├── backend-layers.md         ← Controllers → Services → Actions → DTOs pattern
│   ├── auth-flows.md             ← Clerk, Sanctum, MFA, Social OAuth
│   ├── payment-system.md         ← Flutterwave strategy pattern
│   ├── data-model.md             ← Key entities & relationships
│   └── adr/
│       └── 001-flutterwave-only-gateway.md
├── guides/
│   ├── local-development.md      ← Full local dev setup
│   ├── ai-search.md              ← Natural language + LLM search system
│   ├── virtual-tours.md          ← 360° tour pipeline
│   └── notifications.md          ← Email / Push / WhatsApp channels
├── operations/
│   ├── README.md                 ← Ops index & reading order
│   ├── runbooks/
│   │   ├── deployment.md         ← Deploy to production step-by-step
│   │   ├── rollback.md           ← Emergency rollback procedure
│   │   └── incident-response.md  ← Incident triage & escalation
│   └── (migration, cicd, traefik, docker — see .docs/ for infra guides)
├── security/
│   ├── overview.md               ← Security posture & threat model
│   └── checklist.md              ← Pre-deploy security checklist
├── product/
│   ├── feature-inventory.md      ← Full feature catalog (→ LiveDocs/)
│   ├── roadmap.md                ← Updated roadmap with current progress
│   └── actors/
│       ├── customer.md
│       └── owner-agency.md
├── LiveDocs/                     ← Deep-dive enterprise analyses (auto-generated)
├── audit-2026/                   ← Security & UX audit reports
├── officialDocs/                 ← PDFs, pitch decks, official materials
└── Actors/                       ← Actor-specific feature specs
```

> **Operational infra docs** (server migration, CI/CD, Traefik, Docker) are in [`.docs/`](../.docs/README.md).

---

## 🚀 Quick Navigation

### 👶 New Developer
1. [Architecture Overview](./architecture/overview.md) — understand what you're working with
2. [`README.md`](../README.md) — root README with install & run commands
3. [Backend Layers](./architecture/backend-layers.md) — coding conventions & patterns
4. [Auth Flows](./architecture/auth-flows.md) — how authentication works
5. [Local Development](./guides/local-development.md) — get running in 15 minutes

### 🔧 Day-to-Day Development
| Need | Go to |
|------|-------|
| Add a new API endpoint | [Backend Layers](./architecture/backend-layers.md) |
| Understand auth / roles | [Auth Flows](./architecture/auth-flows.md) |
| Work on payments | [Payment System](./architecture/payment-system.md) |
| Work on AI search | [AI Search Guide](./guides/ai-search.md) |
| Work on 360° tours | [Virtual Tours Guide](./guides/virtual-tours.md) |
| Feature flag usage | [`config/features.php`](../config/features.php) |

### 🚢 DevOps / Deployment
| Need | Go to |
|------|-------|
| Deploy to production | [Deployment Runbook](./operations/runbooks/deployment.md) |
| Emergency rollback | [Rollback Runbook](./operations/runbooks/rollback.md) |
| Incident triage | [Incident Response](./operations/runbooks/incident-response.md) |
| Setup new server | [`.docs/01-migration-serveur.md`](../.docs/01-migration-serveur.md) |
| CI/CD pipeline | [`.docs/02-gitlab-cicd.md`](../.docs/02-gitlab-cicd.md) |
| Traefik / HTTPS | [`.docs/03-traefik-setup.md`](../.docs/03-traefik-setup.md) |

### 🔒 Security
| Need | Go to |
|------|-------|
| Pre-deploy check | [Security Checklist](./security/checklist.md) |
| Security posture | [Security Overview](./security/overview.md) |
| Latest audit | [Enterprise Audit 2026](./LiveDocs/Enterprise-Full-Audit-2026.md) |

### 📦 Product & Roadmap
| Need | Go to |
|------|-------|
| What features exist | [Feature Inventory](./LiveDocs/keyhome_feature_inventory.md) |
| What's planned | [Roadmap](./ROADMAP_IMPROVEMENTS.md) |
| Architecture decisions | [ADRs](./architecture/adr/) |

---

## 🏗️ Platform Architecture at a Glance

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENT LAYER                          │
│  Next.js 16 PWA    │  Filament Admin  │  Filament Agency │
│  (app.keyhome.app) │  (/admin)        │  (/agency)       │
└────────────────────┴──────────────────┴──────────────────┘
                              │
                    ┌─────────▼──────────┐
                    │  Laravel 12 REST API │
                    │  /api/v1/ (66+ ep)  │
                    │  Sanctum + Clerk JWT │
                    └─────────┬───────────┘
           ┌──────────────────┼──────────────────┐
    ┌──────▼──────┐   ┌───────▼──────┐   ┌───────▼──────┐
    │ PostgreSQL  │   │  MeiliSearch │   │    Redis      │
    │ + PostGIS   │   │  (full-text) │   │ (cache/queue) │
    └─────────────┘   └──────────────┘   └──────────────┘
           │
    ┌──────▼──────┐   ┌──────────────┐   ┌──────────────┐
    │ Cloudflare  │   │  Flutterwave │   │   Groq/OAI/  │
    │     R2      │   │  (payments)  │   │   Gemini AI  │
    └─────────────┘   └──────────────┘   └──────────────┘
```

**Queue workers:**
- `critical, payments, emails, default` — `worker` container (timeout 90s, tries 3)
- `tours` — `worker-tours` container (timeout 900s, tries 2, image processing)

---

## 📊 Current Platform Status

| Dimension | Status | Score |
|-----------|--------|-------|
| Backend API | Production-ready | ✅ 92/100 |
| Security | Audited & patched (Apr 2026) | ✅ All P0/P1 fixed |
| Frontend (Next.js) | Production-ready | ✅ |
| Filament Admin | Full-featured | ✅ 26 resources |
| Test Suite | 592+ tests passing | ✅ |
| CI/CD | GitLab → Docker → VPS | ✅ |
| Monitoring | Sentry + Pulse + Nightwatch | ✅ |

---

## 📋 Documentation Health

| File | Status | Last Reviewed |
|------|--------|---------------|
| `README.md` (root) | ✅ Current | Apr 2026 |
| `architecture/overview.md` | ✅ Current | Apr 2026 |
| `architecture/auth-flows.md` | ✅ Current | Apr 2026 |
| `architecture/payment-system.md` | ✅ Current | Apr 2026 |
| `operations/runbooks/deployment.md` | ✅ Current | Apr 2026 |
| `LiveDocs/Enterprise-Full-Audit-2026.md` | ✅ Current | Apr 1, 2026 |
| `audit/audit_backend.md` | ⚠️ Legacy | Feb 2026 — pre-refactor |
| `readme/README_EN.md` | ⚠️ Stale | Feb 2026 — FedaPay refs |
| `readme/audit_webview_bailleur.md` | 🗑️ Obsolete | Bailleur panel removed |
| `docs/ROADMAP_IMPROVEMENTS.md` | ⚠️ Partially stale | Feb 2026 |

---

## 🔑 Key Technical Decisions

| Decision | Choice | Rationale | ADR |
|----------|--------|-----------|-----|
| Payment gateway | Flutterwave only | XAF/XOF native, extensible strategy pattern | [ADR-001](./architecture/adr/001-flutterwave-only-gateway.md) |
| Frontend auth | Clerk JWT → Sanctum exchange | Clerk for OAuth UX, Sanctum for API authorization | — |
| Search | MeiliSearch + PostGIS + LLM | Full-text + geo + natural language, all synergistic | — |
| Admin panels | Filament 4 | Rapid resource generation, MFA built-in | — |
| Session isolation | Role-prefixed tokens (`owner_*`/`client_*`) | Prevents cross-role session leakage | — |

---

## 📞 Support & Escalation

| Issue type | Contact |
|------------|---------|
| Production incident | See [Incident Response Runbook](./operations/runbooks/incident-response.md) |
| Security vulnerability | security@keyhome.app (do NOT open public issue) |
| Feature request | Add to [Roadmap](./ROADMAP_IMPROVEMENTS.md) |

---

*This documentation is maintained by the NeoCraft engineering team.*
*For corrections, open a merge request targeting the `preprod` branch.*
