# KeyHome — Operations Documentation

> **Last updated:** April 2026

---

## Runbooks

| Runbook | Use when |
|---------|---------|
| [Deployment](./runbooks/deployment.md) | Shipping code to production |
| [Rollback](./runbooks/rollback.md) | Reverting a bad deploy |
| [Incident Response](./runbooks/incident-response.md) | Outage / degradation triage |

---

## Infrastructure Guides (in `.docs/`)

These are the step-by-step infra setup guides. Reading order:

| Step | File | Content |
|------|------|---------|
| 0 | [00-conventions-linux.md](../../.docs/00-conventions-linux.md) | Linux FHS conventions, where to place files |
| 1 | [01-migration-serveur.md](../../.docs/01-migration-serveur.md) | Full VPS server migration (2–4 hours) |
| 2 | [02-gitlab-cicd.md](../../.docs/02-gitlab-cicd.md) | GitLab CI/CD pipeline setup |
| 3 | [03-traefik-setup.md](../../.docs/03-traefik-setup.md) | Traefik reverse proxy + SSL |
| 4 | [04-docker-compose-complet.md](../../.docs/04-docker-compose-complet.md) | Full Docker Compose reference |

---

## Common Operational Commands

### Health Check
```bash
# Requires admin Bearer token
curl -H "Authorization: Bearer $ADMIN_TOKEN" https://api.keyhome.app/api/health
```

### View Live Logs
```bash
docker compose logs -f --tail=100 app
docker compose logs -f --tail=100 worker
docker compose logs -f --tail=100 worker-tours
```

### Queue Status
```bash
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
```

### Cache Management
```bash
docker compose exec app php artisan optimize:clear    # clear all
docker compose exec app php artisan optimize          # rebuild all caches
```

### Database
```bash
# Interactive psql
docker compose exec db psql -U postgres keyhome

# Backup
docker compose exec app php artisan backup:run --only-db

# Migration status
docker compose exec app php artisan migrate:status
```

### MeiliSearch
```bash
# Re-index all ads
docker compose exec app php artisan scout:import "App\\Models\\Ad"

# Sync settings
docker compose exec app php artisan meilisearch:sync-settings
```

---

## Monitoring Endpoints

| Tool | URL | Access |
|------|-----|--------|
| Laravel Pulse | `https://api.keyhome.app/pulse` | Admin session |
| Laravel Telescope | `https://api.keyhome.app/telescope` | Admin session (disable in prod if unused) |
| Grafana | `https://grafana.keyhome.app` | Admin credentials |
| Sentry | `https://sentry.io/organizations/neocraft/` | Team login |
| Nightwatch | Laravel Nightwatch dashboard | Team login |
