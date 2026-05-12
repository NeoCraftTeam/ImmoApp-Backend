# Runbook — Emergency Rollback

> **Last updated:** April 2026
> **Use when:** Deployment caused regressions, health check fails, error rate spikes

---

## Decision Tree

```
Deployment caused issues?
    │
    ├── App completely down (500s / no response)
    │   └── → FULL ROLLBACK (below)
    │
    ├── Specific feature broken, rest OK
    │   └── → Feature flag disable first, then hotfix
    │
    └── Performance degradation only
        └── → Scale up worker count, monitor, then rollback if persists
```

---

## Full Rollback — Docker (< 5 minutes)

GitLab Container Registry stores the last N images. Roll back to the previous image tag:

```bash
# SSH to VPS
ssh deploy@your-vps-ip
cd /opt/keyhome

# 1. Find the previous image tag
docker images | grep keyhome-backend | head -5

# 2. Edit docker-compose.yml to use previous tag
# OR use the previous compose file from git:
git log --oneline -5   # find last working commit
git show <commit>:docker-compose.yml > docker-compose.yml.prev
cp docker-compose.yml docker-compose.yml.current
cp docker-compose.yml.prev docker-compose.yml

# 3. Pull and restart with previous image
docker compose pull
docker compose up -d --remove-orphans

# 4. If migration was applied and is incompatible — rollback migration
docker compose exec app php artisan migrate:rollback --step=1

# 5. Clear caches to ensure old code serves old config
docker compose exec app php artisan optimize:clear

# 6. Restart workers
docker compose restart worker worker-tours

# 7. Verify health
curl https://api.keyhome.app/api/health
```

---

## GitLab CI/CD Rollback (Preferred)

If pipeline is accessible:

1. Go to `GitLab → CI/CD → Pipelines`
2. Find the **last successful pipeline** before the bad deploy
3. Click **Retry** on the `deploy` job of that pipeline
4. This re-deploys the exact image that was previously working

---

## Database Rollback

> ⚠️ **Only if the migration actively caused data corruption or breakage.**
> Rolling back a migration that dropped columns is **irreversible** — data is lost.

```bash
# Rollback last migration
docker compose exec app php artisan migrate:rollback --step=1

# Rollback multiple steps
docker compose exec app php artisan migrate:rollback --step=3

# See what would be rolled back (dry run)
docker compose exec app php artisan migrate:status
```

**Best practice:** Always write backward-compatible migrations. Ship the migration separately from the code that uses the new schema when breaking changes are unavoidable.

---

## Notify After Rollback

After rollback is confirmed stable:

1. Post in team channel: "Rolled back deploy `<commit-sha>` — reason: `<brief description>`"
2. Open a post-mortem issue in GitLab
3. Update Sentry alert rules if needed
4. Document in [Incident Log](#incident-log)

---

## Incident Log

| Date | Commit SHA | Cause | Resolution | Duration |
|------|-----------|-------|-----------|---------|
| — | — | — | — | — |

*(Append entries here after each rollback)*
