# KeyHome — Documentation Hub

> **Platform:** Multi-tenant real-estate SaaS for francophone West/Central Africa (XAF/XOF)
> **Stack:** Laravel 12 · Next.js 16 · Filament 4 · PostgreSQL/PostGIS · MeiliSearch · Flutterwave
> **Last updated:** May 2026

---

## 🗂️ Documentation Map

```
docs/
├── README.md                              ← Vous êtes ici — index de navigation
│
├── architecture/                          ← Architecture technique
│   ├── overview.md
│   ├── backend-layers.md
│   ├── auth-flows.md
│   ├── payment-system.md
│   └── adr/001-flutterwave-only-gateway.md
│
├── infrastructure/                        ← DevOps, CI/CD, déploiement VPS
│   ├── DEPLOYEMENT_SETUP_GUIDE.md        ← Guide complet nouveau VPS + CI/CD
│   ├── PREPROD_SETUP.md                  ← Setup environment preprod
│   ├── REVERB_DEPLOY.md                  ← Déploiement WebSocket Reverb
│   └── MONITORING_GUIDE.md               ← Grafana, Prometheus, alertes
│
├── operations/                            ← Exploitation quotidienne
│   ├── README.md
│   ├── ADMIN_BOOTSTRAP_GUIDE.md          ← Bootstrap panel admin
│   ├── PWA_STORE_PUBLICATION_GUIDE.md    ← Publication stores App/Play
│   └── runbooks/
│       ├── deployment.md
│       ├── rollback.md
│       ├── incident-response.md
│       └── TROUBLESHOOTING_IMAGES.md    ← Dépannage images/storage
│
├── features/                              ← Specs fonctionnelles par feature
│   ├── ai-search.md                      ← Recherche NLP multi-LLM
│   ├── KeyHome_360_Tour_Implementation_Guide.md
│   ├── TOUR_3D_IMPLEMENTATION.md
│   ├── VIEWING_SCHEDULING_SPEC.md        ← Calendrier de visites
│   ├── RECOMMENDATIONS_ANALYTICS.md
│   ├── OAUTH_INTEGRATION.md
│   └── 5_Survey_Module_Backend_Plan.md
│
├── integrations/                          ← Intégrations tierces
│   ├── payment-integration.md            ← Flutterwave / Stripe
│   ├── EMAIL_DELIVERABILITY_GUIDE.md
│   └── email-authentication.md           ← SPF/DKIM/DMARC
│
├── product/                               ← Stratégie produit & roadmap
│   ├── KEYHOME_ANALYSIS.md
│   ├── CRO_ANALYSIS.md
│   ├── ACQUISITION_TRACKING.md
│   ├── ROADMAP_IMPROVEMENTS.md
│   ├── keyhome_million_dollar_roadmap.md
│   ├── utm_tracking_implementation_guide.md
│   └── v2-suggestions.md
│
├── ux/                                    ← UX, design, blueprints
│   ├── UX_AUDIT.md
│   ├── ux_design_blueprint.md
│   └── audit_native_ux.md
│
├── security/                              ← Sécurité
│   ├── overview.md
│   └── checklist.md
│
├── audit-2026/                            ← Audits qualité & sécurité
│   ├── Enterprise-Full-Audit-2026.md     (→ LiveDocs/)
│   ├── SECURITY_AUDIT_MARCH_2026.md
│   ├── audit_backend_api.md
│   ├── audit_nextjs_frontend.md
│   ├── audit_filament_panels.md
│   ├── production_readiness_report.md
│   ├── audit_codebase_2026_03_22.md
│   └── codebase_analysis.md
│
├── LiveDocs/                              ← Analyses enterprise (deep-dive)
│   ├── Enterprise-Full-Audit-2026.md
│   ├── keyhome_feature_inventory.md
│   ├── keyhome_million_dollar_roadmap.md
│   └── messaging_doc.md
│
├── marketing/                             ← Contenu social & campagnes
│   └── calendrier-publications-virales-keyhome.md
│
├── officialDocs/                          ← PDFs, pitch decks officiels
│   ├── PITCH_KEYHOME_FR.md
│   └── ADMIN_PANEL_PITCH_FR.md
│
└── Actors/                                ← Specs par acteur
    └── owner.md
```

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
| Work on AI search | [AI Search Guide](./features/ai-search.md) |
| Work on 360° tours | [Virtual Tours Guide](./features/TOUR_3D_IMPLEMENTATION.md) |
| Feature flag usage | [`config/features.php`](../config/features.php) |

### 🚢 DevOps / Deployment
| Need | Go to |
|------|-------|
| Deploy to production | [Deployment Runbook](./operations/runbooks/deployment.md) |
| Emergency rollback | [Rollback Runbook](./operations/runbooks/rollback.md) |
| Incident triage | [Incident Response](./operations/runbooks/incident-response.md) |
| Setup new VPS | [Deployment Setup Guide](./infrastructure/DEPLOYEMENT_SETUP_GUIDE.md) |
| Setup preprod | [Preprod Setup](./infrastructure/PREPROD_SETUP.md) |
| Monitoring | [Monitoring Guide](./infrastructure/MONITORING_GUIDE.md) |

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
| What's planned | [Roadmap](./product/ROADMAP_IMPROVEMENTS.md) |
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
| `docs/product/ROADMAP_IMPROVEMENTS.md` | ⚠️ Partially stale | Feb 2026 |

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
| Feature request | Add to [Roadmap](./product/ROADMAP_IMPROVEMENTS.md) |

---

*This documentation is maintained by the NeoCraft engineering team.*
*For corrections, open a merge request targeting the `preprod` branch.*
