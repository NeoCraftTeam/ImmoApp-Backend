---
name: keyhome-research-auditor
description: >
  Use this skill whenever the user asks to research, audit, gap-analyse, or
  benchmark any feature of the KeyHome real-estate platform (or any similar
  SaaS/proptech app). Triggers on phrases like: "audit our X feature",
  "crawl the internet for best practices on Y", "find gaps in our Z module",
  "compare our implementation to industry standards", "what are we missing in
  our chat / payments / tour3d / search / notifications / moving / auth
  module?", "document expert findings", "check API interoperability", or
  "create a research report on [feature]". Always use this skill before
  answering any in-depth technical or product audit question about KeyHome.
compatibility:
  tools:
    - firecrawl-mcp   # web crawling & scraping
    - web_search      # fallback search
    - bash_tool       # writing output files
    - create_file     # persisting reports
---

# KeyHome Research & Audit Skill

A structured workflow to crawl the internet for expert knowledge on any
KeyHome feature, document findings, and produce an actionable gap-audit report.

---

## OVERVIEW

```
Phase 1 — Identify scope         (which feature(s) to audit)
Phase 2 — Crawl & research       (Firecrawl + web_search)
Phase 3 — Document findings      (structured markdown per domain)
Phase 4 — Gap audit              (compare current impl vs best practice)
Phase 5 — Interoperability check (API contracts, SDK compatibility)
Phase 6 — Deliver report         (single .md file + priority matrix)
```

---

## KEYHOME FEATURE MAP

Reference this map to scope any audit request:

```
MODULE              STACK                          REPORT SECTION
──────────────────────────────────────────────────────────────────
auth                Clerk + Sanctum + JWT          AUTH
real-time chat      Laravel Reverb + Echo          CHAT
payments            Flutterwave + Stripe           PAYMENTS
property listings   Laravel + PostgreSQL + Meilisearch  LISTINGS
3d-tour             Pannellum / PSV + R2           TOUR3D
search-ai           NLP + Meilisearch              SEARCH
notifications       FCM + Sonner + Reverb          NOTIFS
moving-service      Custom + Flutterwave escrow    MOVING
contracts           DomPDF + Laravel               CONTRACTS
geolocation         Mapbox + Nominatim + PostGIS   GEO
cdn-infra           Cloudflare + Vercel + VPS      INFRA
mobile              Flutter + Dart                 MOBILE
analytics           Clarity + Meta/TikTok pixels   ANALYTICS
gamification        Badges + Points                GAMIF
```

---

## PHASE 1 — SCOPE IDENTIFICATION

Before crawling, clarify:

1. **Which module(s)?** — Map user request to the feature map above.
   If ambiguous, default to ALL modules and produce a full audit.
2. **Depth level?**
   - `quick`   → top 3 sources per module, 1-page summary
   - `standard`→ 5-8 sources per module, full gap analysis (DEFAULT)
   - `deep`    → 10+ sources, API contracts, code samples, benchmarks
3. **Focus area?** — e.g., security only, performance only, UX only,
   API interoperability only, or ALL (default).

---

## PHASE 2 — CRAWL & RESEARCH PROTOCOL

### 2.1 Firecrawl targets per module

For each module in scope, crawl these source categories:

```
CATEGORY            EXAMPLE URLS TO CRAWL
────────────────────────────────────────────────────────────────────
Official docs       docs.flutterwave.com, clerk.dev/docs,
                    laravel.com/docs, reverb.laravel.com,
                    docs.stripe.com, firebase.google.com/docs
Best-practice blogs web.dev, smashingmagazine.com, martinfowler.com
Security advisories owasp.org, cve.mitre.org, snyk.io/vuln
SDK changelogs      github.com/[repo]/releases
Competitor analysis producthunt.com, g2.com (proptech category)
API specs           Any OpenAPI/Swagger spec discovered
Community wisdom    stackoverflow.com, laracasts.com, github discussions
Performance studies web.dev/performance, bundlephobia.com
```

### 2.2 Firecrawl usage pattern

```javascript
// Pattern to follow for EACH module:

// Step 1 — Deep crawl the official docs
firecrawl_deep_research({
  query: `${moduleName} best practices 2025 security performance`,
  maxDepth: 3,
  limit: 10
})

// Step 2 — Scrape specific high-value pages
firecrawl_scrape({ url: specificDocUrl, formats: ['markdown'] })

// Step 3 — Search for gap discussions
firecrawl_search({
  query: `${moduleName} common pitfalls security vulnerabilities`,
  limit: 5
})
```

### 2.3 Fallback to web_search

If Firecrawl MCP is unavailable or rate-limited:
```
web_search("${moduleName} best practices 2025 site:owasp.org OR site:web.dev")
web_search("${moduleName} API interoperability issues 2025")
web_search("${moduleName} performance benchmarks real-world")
```

---

## PHASE 3 — DOCUMENT FINDINGS

For each module, populate this template:

```markdown
## MODULE: [name]

### Current KeyHome Implementation
- Stack: [from feature map]
- Version: [latest known]
- Key files: [approximate paths]

### Expert Best Practices Found
| Practice | Source | Priority |
|----------|--------|----------|
| ...      | url    | HIGH/MED/LOW |

### Security Findings
- CVEs / advisories found: [list with CVE IDs]
- OWASP Top 10 relevant items: [list]
- Specific risks for this stack: [list]

### Performance Benchmarks
- Expected p95 latency: [from sources]
- Recommended limits: [e.g., max connections, payload sizes]
- Optimization techniques found: [list]

### API / SDK Notes
- Latest stable version: [version]
- Breaking changes since current: [list]
- Deprecated methods in use: [list]
- Interoperability issues: [list]
```

---

## PHASE 4 — GAP AUDIT

After documenting findings, run this audit checklist for EACH module:

### Security Gaps
```
□ Authentication tokens properly scoped and rotated?
□ WebSocket channels properly authorized (not just authenticated)?
□ File uploads: real MIME validation, not just extension?
□ Rate limiting on ALL write endpoints?
□ IDOR prevention (404 not 403 for other users' resources)?
□ Sensitive data never in client bundles or logs?
□ Webhook signatures verified with timing-safe comparison?
□ CSP headers configured for all external scripts/iframes?
□ SQL injection impossible (parameterized queries / ORM)?
□ XSS sanitization on all user-generated content?
```

### Performance Gaps
```
□ N+1 queries eliminated (eager loading)?
□ Database indexes on all foreign keys and filter columns?
□ Cache strategy defined for read-heavy endpoints?
□ Asset compression (Brotli) enabled?
□ Images in modern formats (WebP/AVIF)?
□ Bundle size within budget (<200KB JS initial load)?
□ API response times <200ms p95 for critical paths?
□ WebSocket reconnection with exponential backoff?
```

### API / Interoperability Gaps
```
□ All external SDK versions pinned and up to date?
□ Webhooks idempotent (duplicate delivery handled)?
□ API versioning strategy defined?
□ Error responses follow consistent schema?
□ Pagination consistent (cursor-based for large datasets)?
□ CORS configured correctly for all origins?
□ OpenAPI/Swagger spec generated (Scramble)?
□ Breaking changes documented for mobile clients?
```

### Feature Completeness Gaps
```
□ Offline support / graceful degradation?
□ Accessibility (WCAG 2.1 AA)?
□ Internationalisation ready (i18n)?
□ Analytics events for all conversion funnels?
□ Error monitoring (Sentry) on all critical paths?
□ Feature flags for progressive rollout?
□ Audit logs for all sensitive operations?
□ GDPR/LPD data deletion workflow?
```

---

## PHASE 5 — INTEROPERABILITY DEEP-DIVE

For each external dependency, verify:

```markdown
### [Service Name] Interoperability Check

| Dimension          | Status | Notes |
|--------------------|--------|-------|
| SDK version        | ✅/⚠️/❌ | Current: X, Latest: Y |
| Auth method        | ✅/⚠️/❌ | e.g., Bearer token, API key |
| Webhook format     | ✅/⚠️/❌ | JSON schema stable? |
| Rate limits        | ✅/⚠️/❌ | Current usage vs limits |
| Cameroon support   | ✅/⚠️/❌ | Specific to KeyHome market |
| Mobile SDK         | ✅/⚠️/❌ | Flutter compatibility |
| CORS configuration | ✅/⚠️/❌ | All origins whitelisted? |
| Data residency     | ✅/⚠️/❌ | Where data is stored |
| SLA                | ✅/⚠️/❌ | Uptime guarantee |
| Deprecation risk   | ✅/⚠️/❌ | Any sunset notices? |
```

**Services to check for KeyHome:**
- Clerk (auth)
- Flutterwave (payments — Cameroon coverage)
- Stripe (international payments)
- Laravel Reverb (WebSockets)
- Cloudflare R2 (storage)
- Firebase FCM (push notifications)
- Meilisearch (search)
- Mapbox / Nominatim (geocoding)
- Pannellum / PSV (3D tour)
- Zota (payment — XM broker reference)

---

## PHASE 6 — REPORT GENERATION

### 6.1 Output format

Save to: `/mnt/user-data/outputs/AUDIT_[MODULE]_[DATE].md`

Structure:
```
# KeyHome Feature Audit — [Module(s)] — [Date]

## Executive Summary (5 bullet points max)
## Critical Findings (must fix before launch)
## Module Reports (one section per module, Phase 3 template)
## Gap Analysis Matrix (Phase 4 checklist results)
## Interoperability Report (Phase 5 table per service)
## Priority Action Plan (ranked by severity × effort)
## Sources & References
```

### 6.2 Priority action plan format

```markdown
## Priority Action Plan

| # | Action | Module | Severity | Effort | Owner |
|---|--------|--------|----------|--------|-------|
| 1 | Fix X  | auth   | 🔴 Critical | 2h  | backend |
| 2 | Add Y  | chat   | 🟡 Medium  | 1 day | frontend |
| 3 | Update Z | infra | 🟢 Low   | 30min | devops |

Severity: 🔴 Critical (security/data loss) | 🟡 Medium (UX/perf) | 🟢 Low (nice-to-have)
Effort: estimated developer hours/days
```

---

## EXECUTION INSTRUCTIONS

When this skill triggers, follow this exact sequence:

```
1. Confirm scope with user (1 message — which modules + depth)
2. Announce: "Starting research crawl for [modules]..."
3. Run Phase 2 (Firecrawl) for each module IN PARALLEL if possible
4. Run Phase 3 (document findings) as crawl results arrive
5. Run Phase 4 (gap audit) using findings + KeyHome context
6. Run Phase 5 (interoperability) for all external services
7. Generate Phase 6 report → save to outputs/
8. Present file + executive summary inline
9. Ask: "Which critical finding should we address first?"
```

**Never skip Phase 4** — the gap audit is the primary deliverable.

**Always cross-reference** findings against KeyHome's known stack
(Laravel 12 + Next.js 15 + PostgreSQL + Redis + Cloudflare R2 +
Laravel Reverb + Clerk + Flutterwave) before flagging gaps.

**Do not flag** something as a gap if it's already implemented
in the KeyHome codebase based on conversation history.

---

## REFERENCE FILES

Read these when needed:

- `references/keyhome-stack.md` — Full stack details + versions
- `references/security-checklist.md` — Expanded OWASP checklist
- `references/api-contracts.md` — Known API shapes for each service
- `references/gap-history.md` — Previously identified + fixed gaps

---

## EXAMPLE TRIGGER PHRASES

```
"Audit our chat module for security gaps"
"Crawl best practices for Laravel Reverb WebSockets"
"What are we missing in our 3D tour implementation?"
"Check if Flutterwave is properly integrated for Cameroon"
"Run a full audit on all KeyHome features"
"Research expert knowledge on real estate app notifications"
"Find interoperability issues between our services"
"Document findings on PSV vs Pannellum for production use"
"Are there any security gaps in our payment flow?"
"Benchmark our search against Meilisearch best practices"
```
