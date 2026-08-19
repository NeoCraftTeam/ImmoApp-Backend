# Runbook — Incident Response

> **Last updated:** April 2026

---

## Severity Levels

| Level | Definition | Response Time | Escalation |
|-------|-----------|--------------|-----------|
| **P0 — Critical** | Full outage, payments down, data loss | Immediate | All hands |
| **P1 — High** | Major feature broken (search, auth, uploads) | < 30 min | Lead + on-call |
| **P2 — Medium** | Feature degraded, workaround exists | < 2 hours | On-call |
| **P3 — Low** | Minor UX issue, cosmetic bug | Next business day | Standard |

---

## Triage Checklist (First 5 Minutes)

```bash
# 1. Is the API responding?
curl -o /dev/null -s -w "%{http_code}" https://api.keyhome.app/api/v1/ads?per_page=1

# 2. Are containers running?
docker compose ps

# 3. Recent error logs
docker compose logs --tail=100 app | grep -E "(ERROR|CRITICAL|Exception)"

# 4. Queue worker healthy?
docker compose logs --tail=50 worker
docker compose logs --tail=50 worker-tours

# 5. Database reachable?
docker compose exec app php artisan tinker --execute="DB::select('SELECT 1')"

# 6. Redis reachable?
docker compose exec app php artisan tinker --execute="Redis::ping()"

# 7. Check Sentry for spike
# → https://sentry.io/organizations/neocraft/issues/

# 8. Check Laravel Pulse
# → https://api.keyhome.app/pulse
```

---

## Common Incidents & Resolutions

### Incident: App container restarting / crash loop

```bash
docker compose logs app --tail=200
# Look for: OOM, PHP fatal error, missing env var, migration failed on start

# If OOM: check memory limits in docker-compose.yml
docker stats

# Quick fix: restart with fresh state
docker compose down app && docker compose up -d app
```

### Incident: Queue jobs stuck / not processing

```bash
# Check failed jobs
docker compose exec app php artisan queue:failed

# Retry all failed jobs
docker compose exec app php artisan queue:retry all

# If worker crashed: restart it
docker compose restart worker

# Nuclear option: flush queue (DESTRUCTIVE — jobs lost)
docker compose exec app php artisan queue:flush
```

### Incident: Payment webhook not processing

```bash
# 1. Check Flutterwave dashboard for webhook delivery attempts
# → https://dashboard.flutterwave.com/webhooks

# 2. Verify webhook route is accessible
curl -X POST https://api.keyhome.app/api/v1/webhooks/flutterwave \
  -H "verif-hash: wrong-hash" -d '{}'
# Expected: 401 (route reachable, signature rejected correctly)

# 3. Check for stuck payment records
docker compose exec app php artisan tinker --execute="
  App\Models\Payment::where('status', 'pending')
    ->where('created_at', '<', now()->subHours(2))
    ->count()
"

# 4. Run cleanup command
docker compose exec app php artisan app:cleanup-stale-payments
```

### Incident: MeiliSearch sync broken (search returning empty)

```bash
# Re-index all ads
docker compose exec app php artisan scout:import "App\\Models\\Ad"

# Sync index settings
docker compose exec app php artisan meilisearch:sync-settings

# Verify index has documents
curl http://localhost:7700/indexes/ads/stats \
  -H "Authorization: Bearer $MEILISEARCH_KEY"
```

### Incident: Emails not received (auth / notifications / invoices)

**Symptômes :** OTP non reçu, mails de notification/facturation absents, `VerifyEmail` silencieux.

```bash
# 1. Diagnostic complet (prod / preprod)
docker compose exec app php artisan mail:diagnose
docker compose exec app php artisan mail:diagnose --send-to=ops-test@keyhome.app

# 2. Transport attendu : Resend en prod, `log` en preprod
#    Vérifier env (ne jamais logger la clé complète) :
docker compose exec app php artisan tinker --execute="
  echo json_encode([
    'mailer' => config('mail.default'),
    'from'   => config('mail.from.address'),
    'resend_key_set' => filled(config('services.resend.key')),
    'queue'  => config('queue.default'),
  ], JSON_PRETTY_PRINT)
"

# 3. Domaine Resend vérifié (sinon 403 silencieux)
#    → https://resend.com/domains — DKIM + SPF verts pour keyhome.app
#    MAIL_FROM_ADDRESS et les 7 MAIL_*_ADDRESS (senders HasSender) doivent
#    appartenir au domaine vérifié, sinon Resend rejette.

# 4. Queue (les mailables sont ShouldQueue sur la queue `emails`)
#    En prod : QUEUE_CONNECTION=redis + after_commit=true (config/queue.php)
#    Vérifier que le worker tourne :
docker compose ps worker
docker compose logs --tail=50 worker

# 5. Jobs échoués
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
# Si Resend 403/429 : corriger RESEND_KEY / domaine puis retry.
# Voir storage/logs/laravel.log pour les exceptions ResendTransport.

# 6. Fallback local
#    Local / preprod peut utiliser MAIL_MAILER=log (mails dans
#    storage/logs/laravel.log) ou SMTP Infomaniak. Valider :
php artisan mail:diagnose --send-to=ton-email@exemple.com
#    Test brut SMTP (hors queue) :
php artisan tinker --execute="Mail::raw('test', fn(\$m) => \$m->to('x@y.z')->subject('test'));"
```

### Incident: Database disk full

```bash
# Check disk usage
df -h
du -sh /var/lib/docker/volumes/*

# Quick: vacuum PostgreSQL
docker compose exec db psql -U postgres keyhome -c "VACUUM ANALYZE;"

# Identify largest tables
docker compose exec db psql -U postgres keyhome -c "
  SELECT relname, pg_size_pretty(pg_total_relation_size(relid))
  FROM pg_catalog.pg_statio_user_tables
  ORDER BY pg_total_relation_size(relid) DESC LIMIT 10;
"

# Prune old activity logs (if `spatie/activitylog` table is large)
docker compose exec app php artisan activitylog:clean --days=90
```

### Incident: Redis out of memory

Redis is configured with `maxmemory 384mb --maxmemory-policy allkeys-lru`. If this is exceeded:

```bash
# Check current memory
docker compose exec redis redis-cli INFO memory | grep used_memory_human

# Flush only cache (NOT sessions — that would log everyone out)
docker compose exec app php artisan cache:clear

# If absolutely necessary (logs everyone out):
docker compose exec redis redis-cli FLUSHALL
```

---

## Escalation Contacts

| Role | Responsibility |
|------|---------------|
| **Lead Engineer** | P0/P1 triage, architecture decisions |
| **DevOps** | Infrastructure, Docker, deployment |
| **Product** | Stakeholder communication |

> For security incidents (breach, data exposure): **immediately** revoke affected tokens and contact security@keyhome.app.

---

## Post-Incident Report Template

```markdown
## Incident Report — [DATE]

**Severity:** P0/P1/P2/P3
**Duration:** HH:MM
**Impact:** [What was affected, how many users]

### Timeline
- HH:MM — Incident detected
- HH:MM — Triage started
- HH:MM — Root cause identified
- HH:MM — Fix applied
- HH:MM — Service restored

### Root Cause
[Technical description]

### Resolution
[What was done to fix it]

### Action Items
- [ ] [Preventive measure 1]
- [ ] [Preventive measure 2]
```
