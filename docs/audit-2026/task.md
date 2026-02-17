# Production Readiness — Master Checklist

## ✅ Backend API — P0 Critical (All Fixed)
- [x] **P0-1**: Payment `initialize` TOCTOU → `lockForUpdate` + PENDING dedup
- [x] **P0-2**: Webhook idempotency → skip terminal states
- [x] **P0-3**: `AdPolicy::update` → allow agent + owner
- [x] **P0-4**: `PaymentPolicy::create` → operator precedence fix
- [x] **P0-5**: `ads_nearby_user` IDOR → ownership check
- [x] **P0-6**: `radius` uncapped → 50km max
- [x] **P0-7**: `UserRequest` Privilege Escalation → restricted `role`

## ✅ Backend API — P1 High (All Fixed)
- [x] **P1-1**: Email uniqueness TOCTOU → catch `UniqueConstraintViolationException`
- [x] **P1-2**: Registration `type` trusted → force `individual` for customer endpoint
- [x] **P1-3**: `per_page` uncapped → clamped to max 100 (3 endpoints)
- [x] **P1-4**: Subscription premature cancellation → moved to `activateSubscription`
- [x] **P1-5**: FedaPay callback URL injection → `urlencode($adId)`
- [x] **P2-8**: `AdRequest` status update → allowed for agents

## ✅ Filament Panels (All Done — Previous Sessions)
- [x] **FC-1**: `mobile-bridge.blade.php` origin check
- [x] **FC-2**: `email_verified_at` read-only
- [x] **FC-3**: Inline `<script>` → external JS
- [x] **FH-1**: MFA on Agency/Bailleur panels
- [x] **FH-2**: `preserveFilenames()` removed
- [x] **FH-3**: `CustomRegister` → DB::transaction
- [x] **FH-4**: `maxSize` + `acceptedFileTypes` on uploads
- [x] **FM-1**: Bailleur tenant isolation
- [x] **FM-2**: Resource deduplication (SharedAdResource)
- [x] **FM-6**: Ad status state machine
- [x] **FL-1**: Typo `Apperçu` → `Aperçu`
- [x] **FL-2**: Badge tooltips in French

## ✅ Mobile Apps (All Quick Wins Done — Previous Sessions)
- [x] **MC-1**: iOS ATS `NSAllowsArbitraryLoads: false` + domain exceptions
- [x] **MC-2**: Remove `http://localhost*` from `originWhitelist` + `NativeService`
- [x] **MH-1**: Remove committed `.env` files + `.env.example`
- [x] **MH-4**: Remove `READ/WRITE_EXTERNAL_STORAGE` permissions
- [x] **MM-6**: Delete `App.js.backup`
- [x] **MM-3**: Error handler sanitized (no `nativeEvent.description`)

## ✅ Next.js Frontend (All Quick Wins Done)
- [x] **NH-1**: `middleware.ts` created for server-side auth guard (session cookie check)
- [x] **NM-3/NM-4**: CSP + `remotePatterns` dev URLs cleaned (commit `b2dfec8`)
- [x] **Hardcoded localhost**: `useAuth.ts` CSRF URL → uses `NEXT_PUBLIC_API_URL`
- [x] **Typo**: Renamed `next config.ts` -> `next.config.ts`

## 🛠 Tooling & QA (Bonus)
- [x] **Integrity Scanner**: Adapted `scripts/laravel-integrity.mjs` for Laravel/Next.js stack
- [x] **QA Pipeline**: Fixed regressions in `tests/quality.sh` pipeline

## 📋 Mid-Term Backlog (Post-Launch)
- [ ] NC-1/NC-2: Migrate localStorage tokens → Sanctum SPA cookies
- [ ] NC-3: Nonce-based CSP
- [ ] MH-2: SSL certificate pinning
- [ ] MH-3: Base64 → URI image upload
- [ ] NH-3: i18n error messages in login
- [ ] NH-4: Rotate Mapbox token
