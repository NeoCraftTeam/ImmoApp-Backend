# Monitoring Stack - Quick Start

## 🎯 Accès rapide

### En développement local
```bash
# Exposer les ports (si pas déjà fait)
cp docker-compose.override.yml.example docker-compose.override.yml

# Démarrer
docker-compose up -d

# Accéder aux services
open http://localhost:3001  # Grafana (admin/admin)
open http://localhost:9090  # Prometheus
```

### En production
```bash
# Via tunnel SSH
ssh -L 3001:localhost:3001 -L 9090:localhost:9090 user@votre-vps
# Puis: http://localhost:3001

# OU configurer Nginx reverse proxy (voir MONITORING_GUIDE.md)
```

## 📊 Dashboards à importer (IDs Grafana)

| Dashboard | ID | Usage |
|-----------|----|----|
| Node Exporter Full | `1860` | ⭐ Métriques serveur complètes |
| Docker Container Metrics | `10619` | ⭐ Performance conteneurs |
| PostgreSQL Database | `9628` | Métriques base de données |
| Redis Dashboard | `11835` | Cache Redis |

**Comment importer:** Grafana → `+` → `Import dashboard` → Entrez l'ID

## 🔔 Alertes configurées

Les alertes suivantes sont actives (voir `.docker/monitoring/prometheus/alerts.yml`) :

**Serveur:**
- ⚠️ CPU > 80% pendant 5min
- 🚨 CPU > 95% pendant 2min
- ⚠️ RAM > 85% pendant 5min
- 🚨 RAM > 95% pendant 2min
- ⚠️ Disque < 20% libre
- 🚨 Disque < 10% libre

**Docker:**
- 🚨 Conteneur arrêté > 1min
- ⚠️ CPU conteneur > 80%
- ⚠️ Mémoire conteneur > 80%

**Database:**
- 🚨 PostgreSQL inaccessible
- ⚠️ > 80 connexions
- 🚨 > 95 connexions
- ⚠️ Cache hit ratio < 90%
- ⚠️ Deadlocks détectés

**Redis:**
- 🚨 Redis inaccessible
- ⚠️ Mémoire > 80%
- ⚠️ Cache miss rate > 50%

## 📖 Documentation complète

Voir [MONITORING_GUIDE.md](../docs/MONITORING_GUIDE.md) pour :
- Configuration détaillée
- Requêtes PromQL utiles
- Création de dashboards personnalisés
- Configuration d'alertes avancées
- Dépannage

## 🔧 Commandes utiles

```bash
# Vérifier les services
docker-compose ps

# Voir les logs
docker-compose logs -f prometheus
docker-compose logs -f grafana

# Redémarrer un service
docker-compose restart prometheus grafana

# Vérifier les alertes Prometheus
curl http://localhost:9090/api/v1/alerts

# Vérifier les targets Prometheus
curl http://localhost:9090/api/v1/targets
```

## ✅ Checklist première utilisation

- [ ] Accès à Grafana réussi
- [ ] Changé le mot de passe admin de Grafana
- [ ] Data source Prometheus vérifiée (vert ✓)
- [ ] Importé dashboard ID 1860
- [ ] Toutes les targets "UP" dans Prometheus
- [ ] Alertes chargées (Prometheus → Alerts)

---

**Pour plus d'informations:** [docs/MONITORING_GUIDE.md](../docs/MONITORING_GUIDE.md)
