# Runbook — Production Deployment

> **Last updated:** April 2026
> **Trigger:** Every merge to `main` branch (via GitLab CI/CD) or manual

---

## Automated Deployment (Normal Path)

Deployments are triggered automatically by GitLab CI/CD on push to `main`.

```
git push origin main
    │
    └── .gitlab-ci.yml pipeline:
        ├── prepare      — Set environment variables
        ├── quality      — PHPStan + Rector + Pint
        ├── build_and_test — Docker build + Pest tests
        ├── deploy       — SSH to VPS, docker compose pull + up
        ├── smoke_test   — Health check endpoint verification
        ├── notify       — Slack/email notification
        └── cleanup      — Remove dangling images
```

Monitor pipeline at: `https://gitlab.com/neocraft/keyhome/-/pipelines`

---

## Manual Deployment (Emergency / Hotfix)

### Pre-deployment Checklist

- [ ] All tests pass locally: `php artisan test`
- [ ] PHPStan clean: `vendor/bin/phpstan analyse`
- [ ] No pending migrations that would break current prod code
- [ ] `.env` production values verified (especially new env vars)
- [ ] Backup taken: `docker compose exec app php artisan backup:run --only-db`

### Step-by-Step

```bash
# 1. SSH to production VPS
ssh deploy@your-vps-ip

# 2. Navigate to application directory
cd /opt/keyhome

# 3. Pull latest images from GitLab Registry
docker compose pull

# 4. Run migrations BEFORE switching traffic (backward-compatible only)
docker compose exec app php artisan migrate --force

# 5. Start new containers (zero-downtime via Traefik)
docker compose up -d --remove-orphans

# 6. Clear all caches
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan event:cache

# 7. Restart queue workers (pick up new code)
docker compose restart worker worker-tours

# 8. Verify health check
curl -H "Authorization: Bearer $ADMIN_TOKEN" https://api.keyhome.app/api/health

# 9. Check logs for 2 minutes
docker compose logs -f --tail=50 app worker
```

### Post-deployment Verification

```bash
# API responds
curl https://api.keyhome.app/api/v1/ads?per_page=1

# Frontend loads
curl -I https://app.keyhome.app

# Queue workers running
docker compose ps worker worker-tours

# No error spike in Sentry
# → https://sentry.io/organizations/neocraft/issues/
```

---

## Zero-Downtime Strategy

Traefik handles traffic switching:
1. New `app` container starts alongside old one
2. Traefik health check passes on new container
3. Traffic shifts to new container
4. Old container stops

**Requirement:** Migrations must be **backward-compatible** (no column renames or drops in same deploy as code change that uses them — use two-phase deploy if needed).

---

## Frontend Deployment (Next.js)

Next.js lives in a separate GitLab repo (`keyhome-frontend-next`), deployed to Vercel or VPS:

```bash
# VPS deployment
cd /opt/keyhome-frontend
git pull origin main
npm ci
npm run build
pm2 restart keyhome-frontend
```

---

## Rollback

If deployment fails, see [Rollback Runbook](./rollback.md).
