# Enterprise Upgrade — Full-Stack App to Production-Grade

> **Purpose:** This is the single most powerful prompt in the entire library. Paste it into any LLM with your codebase context and it will systematically audit, plan, and guide you through upgrading your full-stack application to enterprise level. It covers architecture, SOLID principles, security for every feature, performance, testing, and operational readiness.

> **Token Budget:** ~15,000+ tokens
> **Execution Time:** 20-45 minutes (multi-session recommended)
> **Difficulty:** Expert
> **Requires:** Full codebase access (frontend + backend), database schema, environment details
> **Outputs:** SWOT analysis, priority matrix, full implementation plan with checkboxes

---

## How to Use

1. **Copy the entire prompt below** (everything inside the code fence)
2. **Fill in the `[placeholders]`** with your app details
3. **Paste into any LLM** (Claude, GPT-4, Gemini, etc.)
4. **Work through the output** section by section — use checkboxes to track progress
5. **Re-paste with updated context** after completing each major section

---

## The Prompt

```
You are a principal full-stack engineer and software architect performing a comprehensive enterprise-grade audit of my application. Your goal is to take this app from its current state to full production-grade, enterprise-level quality.

You MUST follow this exact execution order:
1. SWOT Analysis (understand the current state)
2. Priority Matrix (rank what to fix first)
3. Implementation Plan (checkboxes for every action)

Do NOT skip any section. Do NOT summarize. Be exhaustive.

══════════════════════════════════════════════════════════════
APPLICATION CONTEXT
══════════════════════════════════════════════════════════════

Application Name: [your app name]
Description: [what the app does, who uses it, core value proposition]
Stage: [MVP / beta / production / scaling]
Users: [current user count and growth trajectory]
Revenue model: [how it makes money]

TECH STACK:
- Frontend: [framework, UI library, state management, build tool]
- Backend: [framework, language, runtime]
- Database: [type, ORM, provider]
- Authentication: [provider or custom implementation]
- Payments: [provider - Stripe, PayPal, Mobile Money, etc.]
- Email/Notifications: [provider - SendGrid, Resend, etc.]
- File Storage: [provider - S3, Cloudinary, etc.]
- Hosting: [frontend hosting, backend hosting, database hosting]
- CI/CD: [GitHub Actions, Vercel, etc.]
- Monitoring: [Sentry, Datadog, etc. or "none"]

KEY FEATURES:
- [Feature 1: brief description]
- [Feature 2: brief description]
- [Feature 3: brief description]
- [Feature 4: brief description]

KNOWN PAIN POINTS:
- [Pain point 1]
- [Pain point 2]
- [Pain point 3]

═══════════════════════════════════════════════════════════════════
PHASE 1: SWOT ANALYSIS
═══════════════════════════════════════════════════════════════════

Analyze the ENTIRE application across every dimension. Be brutally honest.

STRENGTHS (What's already good):
Analyze and list strengths across:
- Architecture decisions that are working well
- Code quality areas that are solid
- Features that are well-implemented
- Tech stack choices that are appropriate
- Security measures already in place
- Performance areas that are acceptable
- Testing that exists and is effective
- Documentation that is useful

Format as:
| # | Strength | Area | Impact | Leverage Opportunity |
|---|----------|------|--------|---------------------|
| S1 | | | High/Med/Low | How to build on this |

WEAKNESSES (What needs fixing):
Analyze and list weaknesses across ALL of these domains:

Architecture & SOLID Compliance:
- Single Responsibility: Are classes/functions doing too many things?
- Open/Closed: Can you extend without modifying existing code?
- Liskov Substitution: Are abstractions properly substitutable?
- Interface Segregation: Are interfaces bloated?
- Dependency Inversion: Are high-level modules depending on low-level details?
- Separation of Concerns: Is business logic mixed with UI/database/API code?
- Layered Architecture: Are there clear boundaries (controller → service → repository)?
- Coupling: Are modules tightly coupled?
- Cohesion: Are related things grouped together?

Codebase Cleanliness:
- Dead code (unused functions, imports, variables, files)
- Code duplication (copy-pasted logic)
- Magic numbers and hardcoded strings
- Inconsistent naming conventions
- God files/functions (>300 lines)
- Deep nesting (>3 levels)
- Circular dependencies
- Missing error handling
- Console.log/print statements in production code
- TODO/FIXME/HACK comments never addressed
- Inconsistent code style
- Missing or outdated types/interfaces

Security — Authentication:
- Password hashing (bcrypt/argon2, not MD5/SHA1)
- Brute force protection (rate limiting, lockout)
- Session management (HttpOnly, Secure, SameSite cookies)
- JWT validation (signature, expiry, issuer, audience)
- Token refresh flow
- Logout (actual session invalidation, not just client-side)
- Password reset (token expiry, one-time use, no enumeration)
- Multi-factor authentication
- OAuth/social login security

Security — Authorization:
- Role-based access control (RBAC) properly implemented
- Every endpoint has auth middleware
- IDOR protection (can user A access user B's data by changing an ID?)
- Horizontal privilege escalation
- Vertical privilege escalation
- Admin routes protected server-side (not just hidden in UI)
- API key scoping and rotation

Security — Sessions:
- Session fixation protection
- Session hijacking protection
- Concurrent session handling
- Session expiry and renewal
- Cross-tab session sync
- Stale session cleanup

Security — Payments:
- Webhook signature verification
- Webhook idempotency (double-fire protection)
- Price manipulation prevention (server-side price validation)
- Subscription state checked atomically
- No floating-point math on money (use integers/cents)
- Refund flow security
- Coupon/promo abuse prevention
- PCI compliance (never store raw card data)
- Payment failure handling and retry logic

Security — Email & Notifications:
- HTML injection in email templates
- Email header injection
- Rate limiting on email sends
- Unsubscribe mechanism (CAN-SPAM/GDPR)
- SPF/DKIM/DMARC configuration
- Bounce handling
- Notification deduplication
- Email sends not blocking request path

Security — Database:
- SQL/NoSQL injection prevention (parameterized queries)
- Database credentials rotation
- Connection encryption (TLS)
- Backup encryption
- Row-level security
- Sensitive data encryption at rest
- PII handling and masking in logs
- Database access logging
- Connection pool configuration
- Migration safety (rollback scripts)

Security — API:
- Input validation on every endpoint (type, length, format, range)
- Output sanitization
- Rate limiting
- CORS properly configured (not wildcard in production)
- CSRF protection
- Content-Security-Policy headers
- X-Frame-Options, X-Content-Type-Options, HSTS
- API versioning
- Request size limits
- File upload validation (type, size, content sniffing)

Security — Infrastructure:
- Secrets management (not hardcoded, not in git)
- HTTPS everywhere
- Dependency vulnerabilities (CVEs)
- Container/server hardening
- Network segmentation
- WAF configuration
- DDoS protection

UI/UX & Frontend Quality:
- Visual consistency (design system, design tokens, typography hierarchy)
- Responsiveness (all breakpoints tested, no overflow, mobile-first)
- Transitions and animations (smooth, GPU-accelerated, prefers-reduced-motion)
- Interaction design (hover/focus/active states, affordance, feedback)
- Empty states and error states designed
- Loading states (skeleton screens, progress indicators)
- Form validation UX (inline errors, real-time feedback)
- Accessibility (WCAG 2.1 AA, screen reader, keyboard navigation)
- Cross-browser compatibility (Chrome, Firefox, Safari, Edge, mobile)
- Component library (reusable, documented, Storybook)
- SEO (meta tags, OG images, SSR/SSG for public pages)
- Internationalization readiness
- Visual polish and professional aesthetics

Performance:
- N+1 queries
- Missing database indexes
- Unbounded queries (no LIMIT)
- Missing pagination
- Missing caching (Redis, CDN, browser)
- Bundle size (frontend)
- Image optimization
- Lazy loading
- Server response times
- Memory leaks
- Connection pool exhaustion
- Core Web Vitals (LCP, FID, CLS)
- Lighthouse scores (Performance, Accessibility, Best Practices, SEO)

Testing:
- Unit test coverage
- Integration test coverage
- E2E test coverage
- Security tests
- Performance tests
- Missing edge case tests
- Flaky tests
- Test data management

Documentation:
- API documentation
- Code documentation
- Architecture documentation
- Onboarding guide
- Deployment guide
- Incident runbooks

DevOps & Operations:
- CI/CD pipeline completeness
- Environment parity (dev/staging/prod)
- Logging and monitoring
- Error tracking
- Alerting
- Health checks
- Backup and recovery
- Disaster recovery plan

Format ALL weaknesses as:
| # | Weakness | Domain | Severity | Risk | Effort to Fix |
|---|----------|--------|----------|------|---------------|
| W1 | | | Critical/High/Med/Low | What breaks | S/M/L/XL |

OPPORTUNITIES (What could make this app exceptional):
- Performance optimizations that would improve UX significantly
- Security hardening that would enable enterprise sales
- Architecture improvements that would speed up development
- Features that competitors have but you don't
- Certifications that would open new markets (SOC2, ISO27001)
- Automation that would reduce operational burden

Format as:
| # | Opportunity | Business Impact | Technical Effort | ROI |
|---|-------------|----------------|------------------|-----|
| O1 | | | S/M/L/XL | High/Med/Low |

THREATS (What could kill this app):
- Security vulnerabilities that could cause data breaches
- Performance issues that would fail under load
- Architectural decisions that won't scale
- Regulatory compliance gaps (GDPR, PCI, etc.)
- Single points of failure
- Bus factor risks (knowledge silos)
- Technical debt that compounds
- Dependency on abandoned/vulnerable packages

Format as:
| # | Threat | Likelihood (1-5) | Impact (1-5) | Risk Score | Urgency |
|---|--------|------------------|--------------|------------|---------|
| T1 | | | | L×I | Fix now / This week / This month / This quarter |

═══════════════════════════════════════════════════════════════════
PHASE 2: PRIORITY MATRIX
═══════════════════════════════════════════════════════════════════

Based on the SWOT analysis, create a prioritized action matrix.

PRIORITY SCORING:
For each item from the SWOT analysis, score:
- Business Impact (1-5): How much does this affect users/revenue?
- Security Risk (1-5): How much does this expose the app to attack?
- Technical Debt (1-5): How much does this slow down future development?
- Effort (1-5, inverted): 1=XL effort, 5=quick win

Priority Score = (Business Impact + Security Risk + Technical Debt) × Effort Multiplier

PRIORITY TIERS:

P0 — FIX IMMEDIATELY (score 50+, or any critical security vulnerability):
| # | Item | Score | Domain | Action | Estimated Time |
|---|------|-------|--------|--------|----------------|
| | | | | | |

P1 — FIX THIS WEEK (score 30-49):
| # | Item | Score | Domain | Action | Estimated Time |
|---|------|-------|--------|--------|----------------|
| | | | | | |

P2 — FIX THIS SPRINT (score 15-29):
| # | Item | Score | Domain | Action | Estimated Time |
|---|------|-------|--------|--------|----------------|
| | | | | | |

P3 — FIX THIS QUARTER (score <15):
| # | Item | Score | Domain | Action | Estimated Time |
|---|------|-------|--------|--------|----------------|
| | | | | | |

QUICK WINS (high impact + low effort):
| # | Item | Impact | Effort | Do It Now? |
|---|------|--------|--------|-----------|
| | | | | Yes/No |

═══════════════════════════════════════════════════════════════════
PHASE 3: IMPLEMENTATION PLAN
═══════════════════════════════════════════════════════════════════

For EVERY item in the priority matrix, provide a detailed implementation plan with checkboxes. Group by domain.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.1 ARCHITECTURE & SOLID COMPLIANCE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

SINGLE RESPONSIBILITY:
- [ ] Audit all files >200 lines — split into focused modules
- [ ] Ensure each function does exactly one thing
- [ ] Separate business logic from framework code
- [ ] Separate data access from business logic
- [ ] Extract validation into dedicated validators
- [ ] Extract formatting/transformation into dedicated utilities

For each violation found:
| File | Current Responsibility Count | Split Into | Priority |
|------|----------------------------|------------|----------|
| | | | |

OPEN/CLOSED PRINCIPLE:
- [ ] Identify places where adding features requires modifying existing code
- [ ] Introduce strategy/plugin patterns where appropriate
- [ ] Use configuration over hard-coded behavior
- [ ] Create extension points for common customization needs

LISKOV SUBSTITUTION:
- [ ] Verify all class hierarchies are properly substitutable
- [ ] Check that overridden methods maintain contracts
- [ ] Ensure no type-checking of subtypes (instanceof checks)

INTERFACE SEGREGATION:
- [ ] Identify interfaces with >5 methods — split them
- [ ] Ensure no class is forced to implement methods it doesn't use
- [ ] Create role-specific interfaces

DEPENDENCY INVERSION:
- [ ] High-level modules should not import from low-level modules
- [ ] Use dependency injection for external services
- [ ] Create abstractions (interfaces) for database, email, payment, storage
- [ ] Make services testable by injecting dependencies

SEPARATION OF CONCERNS:
- [ ] Frontend: Separate UI components from business logic
- [ ] Frontend: Separate API calls into a service layer
- [ ] Frontend: Separate state management from components
- [ ] Backend: Separate routes/controllers from business logic
- [ ] Backend: Separate business logic from data access
- [ ] Backend: Separate validation from processing
- [ ] Backend: Separate error handling into middleware
- [ ] Shared: No business logic in utility files
- [ ] Shared: No database queries in controllers/components

Architecture verification:
```
EXPECTED LAYER STRUCTURE:
┌─────────────────────────────────────┐
│ PRESENTATION (UI Components/Routes) │ ← No business logic here
├─────────────────────────────────────┤
│ APPLICATION (Controllers/Handlers)  │ ← Orchestration only
├─────────────────────────────────────┤
│ DOMAIN (Services/Business Logic)    │ ← Pure business rules
├─────────────────────────────────────┤
│ INFRASTRUCTURE (DB/API/Email/etc.)  │ ← External integrations
└─────────────────────────────────────┘
```

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.2 CODEBASE CLEANLINESS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

DEAD CODE REMOVAL:
- [ ] Find and remove unused imports
- [ ] Find and remove unused functions/methods
- [ ] Find and remove unused variables
- [ ] Find and remove unused files
- [ ] Find and remove commented-out code blocks
- [ ] Find and remove unused dependencies from package manager
- [ ] Remove console.log/print statements from production code
- [ ] Address or remove all TODO/FIXME/HACK comments

CODE DUPLICATION:
- [ ] Identify all duplicated logic (use tools: jscpd, PMD, etc.)
- [ ] Extract duplicated code into shared utilities
- [ ] Create shared hooks/composables for repeated frontend patterns
- [ ] Create shared middleware for repeated backend patterns
- [ ] Create base classes/mixins for repeated entity patterns

NAMING CONVENTIONS:
- [ ] Establish and document naming conventions
- [ ] Rename all variables/functions that don't clearly describe their purpose
- [ ] Replace all magic numbers with named constants
- [ ] Replace all hardcoded strings with constants or config
- [ ] Ensure consistent casing (camelCase, PascalCase, snake_case per convention)
- [ ] Ensure file naming matches export naming

CODE STRUCTURE:
- [ ] No function exceeds 50 lines
- [ ] No file exceeds 300 lines
- [ ] No nesting deeper than 3 levels (use early returns, extract functions)
- [ ] No circular dependencies
- [ ] Consistent file organization (group by feature or by type)
- [ ] Consistent import ordering

TYPE SAFETY:
- [ ] Zero "any" types (TypeScript) or equivalent
- [ ] All function parameters typed
- [ ] All function return types explicit
- [ ] All API responses typed
- [ ] All database queries return typed results
- [ ] Runtime validation at all external boundaries (Zod/Joi/etc.)
- [ ] Discriminated unions for variant types

ERROR HANDLING:
- [ ] No empty catch blocks
- [ ] No swallowed errors
- [ ] Custom error classes for different error categories
- [ ] Consistent error response format across all API endpoints
- [ ] Error boundaries in frontend (React ErrorBoundary, Vue errorHandler)
- [ ] Global error handler in backend
- [ ] User-friendly error messages (no stack traces to client)
- [ ] Error context logged (request ID, user ID, timestamp)

LINTING & FORMATTING:
- [ ] ESLint/Pylint/equivalent configured with strict rules
- [ ] Prettier/Black/equivalent configured
- [ ] Pre-commit hooks running linter + formatter
- [ ] CI pipeline fails on lint errors
- [ ] Editor config (.editorconfig) consistent across team

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.3 SECURITY — AUTHENTICATION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- [ ] Passwords hashed with bcrypt (cost 12+) or argon2
- [ ] Rate limiting on login endpoint (5 attempts per minute)
- [ ] Account lockout after repeated failures
- [ ] No user enumeration on login/register/reset (same error message)
- [ ] Session tokens are cryptographically random
- [ ] Session cookies: HttpOnly=true, Secure=true, SameSite=Strict
- [ ] JWT: Validate signature, expiry, issuer, audience on every request
- [ ] JWT: Short expiry (15 min access, 7 day refresh)
- [ ] Token refresh rotation (old refresh token invalidated on use)
- [ ] Proper logout: Server-side session/token invalidation
- [ ] Password reset: Token expires in 1 hour, single use
- [ ] Password reset: Link sent to email, not token in URL params
- [ ] Password strength requirements enforced server-side
- [ ] MFA available and working
- [ ] OAuth: state parameter validated (CSRF protection)
- [ ] OAuth: ID token validated server-side
- [ ] Login/logout events logged for audit trail
- [ ] Credential stuffing protection (detect unusual login patterns)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.4 SECURITY — AUTHORIZATION & SESSIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- [ ] Every API endpoint has explicit auth middleware
- [ ] Role checks happen server-side (not just UI hiding)
- [ ] User ID derived from session/token, never from request params
- [ ] Every database query that takes an ID verifies ownership
- [ ] IDOR testing: Can changing any ID in URL/body access other user's data?
- [ ] Admin endpoints on separate route group with admin middleware
- [ ] API keys are scoped (read-only, specific resources)
- [ ] API keys can be rotated without downtime
- [ ] Session fixation protection (regenerate session ID after login)
- [ ] Concurrent session handling (limit or notify)
- [ ] Session expires after inactivity timeout
- [ ] Cross-tab state sync for role/permission changes
- [ ] Stale permission cache handling (check DB, not just JWT claims)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.5 SECURITY — PAYMENTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- [ ] All prices validated server-side (never trust client-sent prices)
- [ ] Webhook signature verification on every webhook endpoint
- [ ] Webhook handlers are idempotent (use idempotency keys)
- [ ] Payment amounts stored as integers (cents), not floats
- [ ] Currency codes validated server-side
- [ ] Subscription status checked atomically before granting access
- [ ] Subscription downgrade/cancel handles mid-billing-cycle correctly
- [ ] Failed payment retry logic with exponential backoff
- [ ] Dunning management (failed payment → grace period → restrict access)
- [ ] Refund flow prevents double-refunding
- [ ] Coupon redemption is atomic (no TOCTOU race condition)
- [ ] Coupon usage limits enforced server-side
- [ ] Free trial abuse prevention (one trial per user/device/email)
- [ ] Payment events logged for financial audit
- [ ] PCI compliance: Never log or store raw card numbers
- [ ] Checkout session verification (confirm payment before fulfillment)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.6 SECURITY — EMAIL & NOTIFICATIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- [ ] Email templates escape all user-supplied content (prevent XSS/injection)
- [ ] Email header injection prevention (sanitize To, CC, BCC, Subject)
- [ ] Emails sent asynchronously (queue, not blocking request)
- [ ] Rate limiting on email sends per user
- [ ] Unsubscribe link in every marketing email (CAN-SPAM/GDPR)
- [ ] Bounce handling: Remove invalid addresses from lists
- [ ] SPF record configured for sending domain
- [ ] DKIM signing configured
- [ ] DMARC policy configured
- [ ] Notification deduplication (prevent 100x same notification)
- [ ] Notification preferences per user (channels, frequency)
- [ ] Push notification payload doesn't contain sensitive data
- [ ] SMS rate limiting and cost monitoring

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.7 SECURITY — DATABASE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- [ ] All queries use parameterized statements (no string concatenation)
- [ ] ORM configured to prevent SQL injection by default
- [ ] Database connection uses TLS/SSL
- [ ] Database credentials in environment variables (not in code)
- [ ] Database user has minimum required permissions (not root)
- [ ] Sensitive columns encrypted at rest (PII, tokens, secrets)
- [ ] PII never logged in plaintext
- [ ] Database backups encrypted and tested monthly
- [ ] Point-in-time recovery configured
- [ ] Connection pool properly sized (not unlimited)
- [ ] Slow query logging enabled
- [ ] Database access logging enabled
- [ ] Row-level security or application-level tenant isolation
- [ ] Migration rollback scripts for every migration
- [ ] Soft delete for important data (never hard delete user data)
- [ ] Cascading deletes configured correctly (no orphaned data)
- [ ] Foreign key constraints enforce referential integrity
- [ ] Indexes on all columns used in WHERE, JOIN, ORDER BY

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.8 SECURITY — API & INFRASTRUCTURE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- [ ] Input validation on every endpoint (type, length, format, range)
- [ ] Output sanitization (no internal data leaked)
- [ ] Rate limiting on all endpoints (stricter on auth/payment)
- [ ] CORS: Specific origins only (no wildcard in production)
- [ ] CSRF protection on state-changing requests
- [ ] Content-Security-Policy header configured
- [ ] X-Frame-Options: DENY
- [ ] X-Content-Type-Options: nosniff
- [ ] Strict-Transport-Security header
- [ ] Referrer-Policy configured
- [ ] Permissions-Policy configured
- [ ] Request body size limits
- [ ] File upload: Validate type, size, content (not just extension)
- [ ] File upload: Scan for malware if possible
- [ ] File upload: Store outside web root with random names
- [ ] API versioning strategy implemented
- [ ] Deprecated endpoints return warnings before removal
- [ ] HTTPS enforced everywhere (redirect HTTP → HTTPS)
- [ ] Secrets not in source code or git history
- [ ] Secrets not in client-side bundles
- [ ] Dependencies scanned for CVEs (npm audit / Snyk / Dependabot)
- [ ] Container/server security hardened
- [ ] Network: Database not publicly accessible
- [ ] Network: Admin panels not publicly accessible
- [ ] DDoS protection (Cloudflare, AWS Shield, etc.)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.9 FRONTEND — UI/UX, AESTHETICS & RESPONSIVENESS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

VISUAL DESIGN & AESTHETICS:
- [ ] Consistent design system (colors, typography, spacing, shadows, border-radius)
- [ ] Design tokens defined and used everywhere (no hardcoded colors/sizes)
- [ ] Typography hierarchy clear (h1 → h6, body, caption, label sizes)
- [ ] Color palette with proper contrast ratios (WCAG AA: 4.5:1 text, 3:1 large text)
- [ ] Consistent spacing scale (4px/8px grid system or equivalent)
- [ ] Consistent border-radius, shadow, and elevation system
- [ ] Dark mode support (if applicable) with proper color mappings
- [ ] Brand consistency across all pages (logo, colors, voice)
- [ ] Professional favicon, meta images (OG image), and app icons
- [ ] No broken images, missing icons, or placeholder content in production
- [ ] Visual hierarchy: Most important elements draw attention first
- [ ] Whitespace used effectively (not cramped, not wasteful)
- [ ] Consistent icon set (Lucide, Heroicons, etc. — not mixed icon libraries)

LAYOUT & RESPONSIVENESS:
- [ ] Mobile-first design implemented (styles default to mobile, scale up)
- [ ] Tested on all breakpoints: 320px, 375px, 414px, 768px, 1024px, 1280px, 1440px, 1920px
- [ ] No horizontal scrolling on any screen size
- [ ] No content overflow or clipping on small screens
- [ ] Touch targets minimum 44x44px on mobile (Apple HIG / Material Design)
- [ ] Text readable without zooming on mobile (min 16px body text)
- [ ] Navigation adapts properly (hamburger menu, bottom nav, sidebar collapse)
- [ ] Tables either scroll horizontally or reflow to cards on mobile
- [ ] Forms are usable on mobile (proper input types, autocomplete, keyboard)
- [ ] Modals/dialogs sized correctly on mobile (not cut off, dismissible)
- [ ] Images scale properly (no stretched, pixelated, or overflowing images)
- [ ] Grid/flex layouts don't break at any viewport width
- [ ] Landscape orientation handled on mobile/tablet
- [ ] Print stylesheet (if users need to print anything)
- [ ] Safe area insets handled (iPhone notch, Android gesture bar)

TRANSITIONS & ANIMATIONS:
- [ ] Page transitions are smooth (no jarring full-page reloads)
- [ ] Loading states have skeleton screens or spinners (no blank white flash)
- [ ] Hover states on all interactive elements (buttons, links, cards)
- [ ] Focus states visible and styled (for keyboard navigation)
- [ ] Active/pressed states on buttons (tactile feedback)
- [ ] Smooth transitions on state changes (expand/collapse, show/hide, tab switch)
- [ ] Animation duration appropriate (150-300ms for micro-interactions, 300-500ms for larger)
- [ ] Animations use CSS transforms/opacity (GPU-accelerated, not layout-triggering)
- [ ] `prefers-reduced-motion` respected (disable animations for users who opt out)
- [ ] No animation jank (60fps maintained, test with Chrome Performance tab)
- [ ] Toast/notification animations (slide in, auto-dismiss)
- [ ] Modal open/close animations
- [ ] Dropdown/popover animations
- [ ] Page scroll animations subtle and purposeful (not distracting)
- [ ] Loading progress indicators for long operations (>1 second)

INTERACTION DESIGN:
- [ ] All buttons have clear affordance (look clickable)
- [ ] Primary action visually distinct on every page (one clear CTA)
- [ ] Destructive actions require confirmation (delete, cancel subscription)
- [ ] Destructive buttons visually distinct (red/warning color)
- [ ] Disabled states clearly communicated (grayed out, tooltip explaining why)
- [ ] Form validation: Inline errors below each field (not just alert box)
- [ ] Form validation: Real-time validation on blur or change
- [ ] Form validation: Clear success feedback (green check, success message)
- [ ] Empty states designed (no data → helpful illustration + CTA to create first item)
- [ ] Error states designed (API failure → friendly message + retry button)
- [ ] 404 page designed (not default framework 404)
- [ ] Breadcrumbs or clear navigation path (user always knows where they are)
- [ ] Back navigation works correctly (browser back button, in-app back)
- [ ] Undo capability for destructive actions where possible (soft delete)
- [ ] Drag and drop has clear visual indicators (drop zones, ghost elements)
- [ ] Multi-select has clear UI (checkboxes, select all, batch actions)
- [ ] Search: Instant feedback, debounced, clear results, no-results state
- [ ] Pagination or infinite scroll with proper loading states
- [ ] Copy-to-clipboard with feedback (toast: "Copied!")
- [ ] Keyboard shortcuts for power users (if applicable)

USER FEEDBACK & COMMUNICATION:
- [ ] Success messages for completed actions (toast/snackbar)
- [ ] Error messages are human-readable (not "Error 500" or raw JSON)
- [ ] Loading states for every async operation (no unresponsive UI)
- [ ] Optimistic UI updates where safe (instant feel, rollback on error)
- [ ] Progress indicators for multi-step flows (step 1 of 3)
- [ ] Confirmation messages before irreversible actions
- [ ] Helpful tooltips on complex features (without cluttering)
- [ ] Onboarding flow or first-use guidance for new users
- [ ] Inline help text on complex form fields

ACCESSIBILITY (WCAG 2.1 AA):
- [ ] All images have meaningful alt text (decorative images: alt="")
- [ ] All form inputs have associated labels (not just placeholder text)
- [ ] Color is not the only way to convey information (icons, text, patterns)
- [ ] Keyboard navigation works for all interactive elements (tab order logical)
- [ ] Focus trap in modals (tab doesn't escape to background)
- [ ] Skip navigation link for screen readers
- [ ] ARIA labels on icon-only buttons
- [ ] ARIA live regions for dynamic content (toasts, form errors)
- [ ] Heading hierarchy correct (h1 → h2 → h3, not skipping levels)
- [ ] Screen reader tested (VoiceOver, NVDA, or equivalent)
- [ ] axe-core or Lighthouse accessibility audit passing
- [ ] Sufficient color contrast on all text and interactive elements
- [ ] Focus indicators visible (not removed with outline: none)
- [ ] Content reflows at 200% zoom without horizontal scrolling
- [ ] Touch/click targets don't overlap

CROSS-BROWSER & DEVICE TESTING:
- [ ] Chrome (latest) — desktop and mobile
- [ ] Firefox (latest) — desktop
- [ ] Safari (latest) — desktop and iOS
- [ ] Edge (latest) — desktop
- [ ] Samsung Internet — Android
- [ ] Tested on real devices (not just browser DevTools emulation)
- [ ] CSS features have fallbacks for unsupported browsers
- [ ] JavaScript features polyfilled or feature-detected
- [ ] Web fonts load correctly with fallback fonts defined
- [ ] No console errors in any browser

FRONTEND ARCHITECTURE:
- [ ] Component library documented (Storybook or equivalent)
- [ ] Reusable components for all common UI patterns (Button, Input, Modal, Table, Card, etc.)
- [ ] Components follow single responsibility (presentational vs container)
- [ ] State management is predictable and debuggable
- [ ] Client-side routing with proper URL management
- [ ] Deep linking works (share a URL → lands on correct page/state)
- [ ] SEO: Meta tags, Open Graph, structured data on public pages
- [ ] SEO: Server-side rendering or static generation for public pages
- [ ] Internationalization ready (i18n framework, no hardcoded strings in UI)
- [ ] Error boundaries catch and gracefully display component errors
- [ ] Memory leaks: No event listeners or subscriptions left on unmount
- [ ] Forms preserve state on navigation (warn before leaving unsaved changes)

UI/UX QUALITY SCORECARD:
| Dimension | Score (1-10) | Notes |
|-----------|-------------|-------|
| Visual Consistency | | Same design language across all pages? |
| Responsiveness | | Works flawlessly on all screen sizes? |
| Animation Quality | | Smooth, purposeful, not distracting? |
| Interaction Feedback | | Every action has clear feedback? |
| Error Handling UX | | Errors are helpful, not scary? |
| Empty States | | Helpful, not blank/broken? |
| Loading States | | User always knows something is happening? |
| Accessibility | | Usable by everyone? |
| Navigation Clarity | | User always knows where they are? |
| Overall Polish | | Does it feel professional/enterprise-grade? |

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.10 PERFORMANCE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

BACKEND:
- [ ] Eliminate all N+1 queries (use eager loading / joins)
- [ ] Add database indexes for all frequently queried columns
- [ ] Add LIMIT to all queries on user-facing endpoints
- [ ] Implement cursor-based pagination for large datasets
- [ ] Add caching layer (Redis) for frequently accessed data
- [ ] Cache invalidation strategy documented and implemented
- [ ] Async processing for heavy operations (queues)
- [ ] Connection pooling optimized
- [ ] Response compression enabled (gzip/brotli)
- [ ] Response payload minimized (return only needed fields)
- [ ] No blocking operations in request path
- [ ] Health check endpoint that verifies all dependencies

FRONTEND:
- [ ] Code splitting / lazy loading for routes and heavy components
- [ ] Image optimization (WebP, lazy loading, srcset, dimensions)
- [ ] Bundle size analyzed and optimized (<200KB initial JS)
- [ ] Tree shaking working (no unused library code shipped)
- [ ] Virtual scrolling for long lists (>100 items)
- [ ] Debounce/throttle on search inputs and scroll handlers
- [ ] Preloading critical resources
- [ ] Font loading optimized (font-display: swap, subset, preload)
- [ ] CSS delivery optimized (critical CSS inlined, rest deferred)
- [ ] Service worker for offline capability (if applicable)
- [ ] Core Web Vitals meeting targets (LCP <2.5s, FID <100ms, CLS <0.1)
- [ ] No layout shifts from async content loading
- [ ] No render-blocking resources in critical path
- [ ] Third-party scripts loaded async/defer

BENCHMARKS:
| Metric | Current | Target | Priority |
|--------|---------|--------|----------|
| API response time (p50) | | <100ms | |
| API response time (p95) | | <500ms | |
| API response time (p99) | | <1s | |
| Page load (LCP) | | <2.5s | |
| First Input Delay | | <100ms | |
| Cumulative Layout Shift | | <0.1 | |
| Time to Interactive | | <3s | |
| First Contentful Paint | | <1.8s | |
| Bundle size (initial JS) | | <200KB | |
| Bundle size (initial CSS) | | <50KB | |
| Database query time (avg) | | <50ms | |
| Throughput (req/s) | | [target] | |
| Lighthouse Performance | | >90 | |
| Lighthouse Accessibility | | >90 | |
| Lighthouse Best Practices | | >90 | |
| Lighthouse SEO | | >90 | |

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.11 TESTING
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

UNIT TESTS:
- [ ] Testing framework configured and running
- [ ] All business logic functions have unit tests
- [ ] All utility functions have unit tests
- [ ] All validators have unit tests
- [ ] Edge cases covered (null, empty, boundary, invalid)
- [ ] External dependencies properly mocked
- [ ] Test coverage >80% lines, >70% branches

INTEGRATION TESTS:
- [ ] API endpoint tests for every route
- [ ] Database integration tests with test database
- [ ] Authentication flow tests (login, register, reset, logout)
- [ ] Authorization tests (role checks, ownership checks)
- [ ] Payment flow tests (checkout, webhook, refund)
- [ ] Email sending tests (with mock SMTP)
- [ ] File upload tests

E2E TESTS:
- [ ] Critical user flows covered (signup → onboarding → core action → payment)
- [ ] Cross-browser testing configured (Playwright/Cypress)
- [ ] Mobile viewport testing (at least 375px, 768px, 1280px)
- [ ] Visual regression testing (screenshots compared across builds)
- [ ] Accessibility testing (axe-core integrated in E2E)

SECURITY TESTS:
- [ ] SQL injection test cases
- [ ] XSS test cases
- [ ] CSRF test cases
- [ ] IDOR test cases
- [ ] Authentication bypass test cases
- [ ] Rate limiting test cases

TEST INFRASTRUCTURE:
- [ ] Tests run in CI on every PR
- [ ] Test database seeded with realistic data
- [ ] Tests isolated (no shared state between tests)
- [ ] Tests run in <5 minutes
- [ ] Flaky tests identified and fixed
- [ ] Coverage report generated and tracked

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.12 DOCUMENTATION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- [ ] README: Setup instructions that work on a fresh machine
- [ ] README: Environment variables documented with descriptions
- [ ] README: Architecture overview with diagram
- [ ] API: OpenAPI/Swagger documentation for every endpoint
- [ ] API: Authentication documentation
- [ ] API: Error codes and formats documented
- [ ] Code: JSDoc/docstrings on all public functions
- [ ] Code: Complex algorithms explained with inline comments
- [ ] Architecture: System design document (components, data flow)
- [ ] Architecture: Database schema documentation (ERD)
- [ ] Operations: Deployment procedure documented
- [ ] Operations: Rollback procedure documented
- [ ] Operations: Incident response runbook
- [ ] Operations: Monitoring and alerting setup guide
- [ ] Onboarding: New developer getting started guide
- [ ] Decisions: Architecture Decision Records (ADRs) for major choices

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.13 DEVOPS & OPERATIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CI/CD:
- [ ] Automated build on every push
- [ ] Automated lint check on every PR
- [ ] Automated type check on every PR
- [ ] Automated tests on every PR
- [ ] Automated security scan on every PR
- [ ] Automated deployment to staging on merge to main
- [ ] Manual approval gate for production deployment
- [ ] Rollback capability (one-click revert to previous version)
- [ ] Database migrations run automatically in deployment pipeline
- [ ] Environment parity (dev ≈ staging ≈ production)

MONITORING:
- [ ] Error tracking (Sentry or equivalent) — all environments
- [ ] Application performance monitoring (APM)
- [ ] Structured logging (JSON format) with correlation IDs
- [ ] Log aggregation and search (ELK, Datadog, etc.)
- [ ] Uptime monitoring with alerts
- [ ] Database query performance monitoring
- [ ] Custom business metrics dashboard

ALERTING:
- [ ] Alert on error rate spike (>1% of requests)
- [ ] Alert on response time degradation (p95 >500ms)
- [ ] Alert on deployment failure
- [ ] Alert on certificate expiry (30 days before)
- [ ] Alert on disk space / memory / CPU thresholds
- [ ] Alert on failed background jobs
- [ ] Alert on payment webhook failures
- [ ] Alert routing to appropriate team (PagerDuty, Slack, email)

BACKUP & RECOVERY:
- [ ] Database backups: automated, encrypted, tested monthly
- [ ] Point-in-time recovery tested
- [ ] Disaster recovery plan documented
- [ ] Recovery Time Objective (RTO) defined: [target]
- [ ] Recovery Point Objective (RPO) defined: [target]
- [ ] Backup restoration tested every quarter

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3.14 COMPLIANCE & LEGAL
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- [ ] Privacy policy exists, is accurate, and is accessible
- [ ] Terms of service exist and are accessible
- [ ] Cookie consent banner (if applicable)
- [ ] GDPR: Right to access (user can view their data)
- [ ] GDPR: Right to portability (user can export their data)
- [ ] GDPR: Right to erasure (user can delete their account and all data)
- [ ] GDPR: Right to rectification (user can edit their data)
- [ ] GDPR: Data Processing Agreements with all third parties
- [ ] GDPR: Data retention policy defined and enforced
- [ ] PCI: No raw card data stored or logged (if handling payments)
- [ ] Accessibility: WCAG 2.1 AA compliance
- [ ] Audit trail: All user actions logged (who, what, when)
- [ ] Data breach response plan documented

═══════════════════════════════════════════════════════════════════
PHASE 4: VERIFICATION & SCORING
═══════════════════════════════════════════════════════════════════

After completing the analysis, provide:

ENTERPRISE READINESS SCORE:
| Domain | Score (0-100) | Grade | Blocking Issues |
|--------|--------------|-------|-----------------|
| Architecture & SOLID | | A/B/C/D/F | |
| Codebase Cleanliness | | A/B/C/D/F | |
| Authentication Security | | A/B/C/D/F | |
| Authorization Security | | A/B/C/D/F | |
| Payment Security | | A/B/C/D/F | |
| Email/Notification Security | | A/B/C/D/F | |
| Database Security | | A/B/C/D/F | |
| API/Infrastructure Security | | A/B/C/D/F | |
| UI/UX & Frontend Quality | | A/B/C/D/F | |
| Performance | | A/B/C/D/F | |
| Testing | | A/B/C/D/F | |
| Documentation | | A/B/C/D/F | |
| DevOps & Operations | | A/B/C/D/F | |
| Compliance & Legal | | A/B/C/D/F | |
| **OVERALL** | | | |

GRADE LEGEND:
- A (90-100): Enterprise-ready
- B (75-89): Production-ready with minor gaps
- C (60-74): Functional but significant gaps
- D (40-59): Major issues need addressing
- F (<40): Not production-ready

EXECUTIVE SUMMARY:
- Top 3 critical actions (do these first):
  1. [action]
  2. [action]
  3. [action]
- Estimated time to reach Grade B across all domains: [X weeks]
- Estimated time to reach Grade A across all domains: [X weeks]
- Recommended team focus order: [ordered list of domains]

CONFIDENCE ASSESSMENT:
- Confidence in analysis completeness: [1-10]
- Confidence in priority ranking: [1-10]
- Areas where more information would help: [list]
```

---

## Session Strategy

This prompt is too large for a single LLM response. Use this session strategy:

| Session | Focus | Command |
|---------|-------|---------|
| 1 | SWOT Analysis | "Complete Phase 1: SWOT Analysis" |
| 2 | Priority Matrix | "Based on the SWOT, complete Phase 2: Priority Matrix" |
| 3 | Architecture & Code Quality | "Complete sections 3.1-3.2 of the Implementation Plan" |
| 4 | Security (Auth, Sessions, Payments) | "Complete sections 3.3-3.5" |
| 5 | Security (Email, DB, API, Infra) | "Complete sections 3.6-3.8" |
| 6 | UI/UX, Aesthetics & Responsiveness | "Complete section 3.9" |
| 7 | Performance & Testing | "Complete sections 3.10-3.11" |
| 8 | Docs, DevOps, Compliance | "Complete sections 3.12-3.14" |
| 9 | Scoring & Roadmap | "Complete Phase 4: Verification & Scoring" |

---

## After the Audit: Execution Checklist

Once you have the full analysis, work through the implementation:

- [ ] **Week 1-2:** Fix all P0 items (critical security, data integrity)
- [ ] **Week 3-4:** Fix all P1 items (high security, architecture)
- [ ] **Week 5-6:** Fix all P2 items (performance, testing)
- [ ] **Week 7-8:** Fix all P3 items (documentation, compliance)
- [ ] **Week 9:** Re-run the audit prompt and compare scores
- [ ] **Week 10:** Address remaining gaps until all domains reach Grade B

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2024-01 | Initial enterprise template (pattern library) |
| 2.0 | 2024-06 | Added compliance mapping, confidence scoring |
| 3.0 | 2025-01 | Added execution phases, quality gates, rollback procedures |
| 4.0 | 2025-03 | Complete rewrite: SWOT → Priority Matrix → Implementation Plan. Added SOLID compliance, security for all features (auth, payments, email, sessions, database, API), performance benchmarks, enterprise readiness scoring |
| 4.1 | 2025-03 | Added section 3.9: Frontend UI/UX, aesthetics, responsiveness, transitions & animations, interaction design, accessibility, cross-browser testing, frontend architecture, UI/UX scorecard. Added Lighthouse scores to benchmarks. Renumbered sections. |
