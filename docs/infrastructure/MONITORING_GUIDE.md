# Guide d'utilisation de Grafana & Prometheus

## 📊 Vue d'ensemble

Votre stack de monitoring comprend :
- **Prometheus** : Collecte et stocke les métriques
- **Grafana** : Visualisation des métriques avec des dashboards
- **Node Exporter** : Métriques du serveur (CPU, RAM, disque)
- **cAdvisor** : Métriques des conteneurs Docker
- **Postgres Exporter** : Métriques de la base de données
- **Redis Exporter** : Métriques de Redis

---

## 🚀 Démarrage rapide

### 1. Accès en développement local

```bash
# Assurez-vous que docker-compose.override.yml existe
cp docker-compose.override.yml.example docker-compose.override.yml

# Démarrez tous les services
docker-compose up -d

# Vérifiez que tout fonctionne
docker-compose ps
```

**URLs d'accès :**
- **Grafana** : http://localhost:3001
  - Username : `admin`
  - Password : `admin` (ou la valeur de `GRAFANA_PASSWORD` dans votre `.env`)
- **Prometheus** : http://localhost:9090

### 2. Accès en production (VPS)

En production, les ports ne sont pas exposés directement. Vous avez deux options :

#### Option A : Accès via tunnel SSH (temporaire)
```bash
# Depuis votre machine locale
ssh -L 3001:localhost:3001 -L 9090:localhost:9090 user@votre-vps

# Puis accédez à :
# - Grafana: http://localhost:3001
# - Prometheus: http://localhost:9090
```

#### Option B : Configurer un reverse proxy Nginx (recommandé)

Ajoutez dans votre configuration Nginx du VPS :

```nginx
# /etc/nginx/sites-available/keyhome

# Grafana
location /grafana/ {
    proxy_pass http://keyhome-grafana:3000/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}

# Prometheus (optionnel, gardez-le privé si possible)
location /prometheus/ {
    proxy_pass http://keyhome-prometheus:9090/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```

Puis redémarrez Nginx :
```bash
sudo nginx -t
sudo systemctl reload nginx
```

Accédez via : `https://votre-domaine.com/grafana/`

---

## 📈 Utilisation de Grafana

### Première connexion

1. **Connectez-vous** : http://localhost:3001
   - Username : `admin`
   - Password : `admin` (changez-le immédiatement!)

2. **La source de données Prometheus est déjà configurée** grâce au provisioning automatique

### Dashboards pré-configurés

Un dashboard pour Docker a déjà été importé automatiquement :
- **Docker & System Monitoring** : Vue d'ensemble des conteneurs

### Importer des dashboards depuis Grafana.com

Grafana offre des milliers de dashboards communautaires prêts à l'emploi :

1. **Cliquez sur** `+` → `Import dashboard`

2. **Dashboards recommandés** :

   | Dashboard | ID | Description |
   |-----------|----|----|
   | Node Exporter Full | `1860` | Métriques complètes du serveur |
   | Docker Container & Host Metrics | `10619` | Vue détaillée des conteneurs |
   | PostgreSQL Database | `9628` | Métriques PostgreSQL |
   | Redis Dashboard | `11835` | Métriques Redis |
   | cAdvisor | `14282` | Performance des conteneurs |

3. **Pour importer** :
   ```
   - Entrez l'ID (ex: 1860)
   - Cliquez "Load"
   - Sélectionnez "Prometheus" comme data source
   - Cliquez "Import"
   ```

### Créer votre propre dashboard

1. **Cliquez sur** `+` → `Create Dashboard`
2. **Ajoutez un panel** → `Add visualization`
3. **Sélectionnez** `Prometheus` comme data source
4. **Écrivez une requête PromQL** (voir exemples ci-dessous)

---

## 🔍 Requêtes Prometheus utiles (PromQL)

### Métriques du serveur (Node Exporter)

```promql
# Utilisation CPU (%)
100 - (avg by (instance) (irate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)

# Utilisation RAM (%)
100 * (1 - ((node_memory_MemAvailable_bytes) / (node_memory_MemTotal_bytes)))

# Espace disque libre (GB)
node_filesystem_avail_bytes{mountpoint="/"} / 1024 / 1024 / 1024

# Trafic réseau (bytes/s)
rate(node_network_receive_bytes_total[5m])
rate(node_network_transmit_bytes_total[5m])

# Load average
node_load1
node_load5
node_load15
```

### Métriques Docker (cAdvisor)

```promql
# Utilisation CPU par conteneur (%)
rate(container_cpu_usage_seconds_total{name=~".+"}[5m]) * 100

# Utilisation mémoire par conteneur (MB)
container_memory_usage_bytes{name=~".+"} / 1024 / 1024

# Trafic réseau par conteneur
rate(container_network_receive_bytes_total{name=~".+"}[5m])
rate(container_network_transmit_bytes_total{name=~".+"}[5m])

# Nombre de conteneurs en cours d'exécution
count(container_last_seen{name=~".+"})
```

### Métriques PostgreSQL

```promql
# Connexions actives
pg_stat_database_numbackends{datname="votre_base"}

# Transactions par seconde
rate(pg_stat_database_xact_commit{datname="votre_base"}[5m])

# Taille de la base de données (MB)
pg_database_size_bytes{datname="votre_base"} / 1024 / 1024

# Cache hit ratio (devrait être > 90%)
100 * (sum(pg_stat_database_blks_hit) / (sum(pg_stat_database_blks_hit) + sum(pg_stat_database_blks_read)))

# Requêtes les plus lentes (nécessite pg_stat_statements)
pg_stat_statements_mean_time_seconds
```

### Métriques Redis

```promql
# Utilisation mémoire (MB)
redis_memory_used_bytes / 1024 / 1024

# Connexions
redis_connected_clients

# Opérations par seconde
rate(redis_commands_processed_total[5m])

# Hit rate du cache
rate(redis_keyspace_hits_total[5m]) / (rate(redis_keyspace_hits_total[5m]) + rate(redis_keyspace_misses_total[5m]))
```

---

## 🔔 Configurer des alertes

### 1. Dans Grafana

1. **Ouvrez un dashboard** et **sélectionnez un panel**
2. **Cliquez sur** `Alert` → `Create alert rule from this panel`
3. **Configurez la condition**, par exemple :
   ```
   CPU > 80% pendant 5 minutes
   ```
4. **Ajoutez un canal de notification** (Email, Slack, etc.)

### 2. Dans Prometheus (recommandé pour les alertes critiques)

Créez `.docker/monitoring/prometheus/alerts.yml` :

```yaml
groups:
  - name: server_alerts
    interval: 30s
    rules:
      # CPU élevé
      - alert: HighCPUUsage
        expr: 100 - (avg by (instance) (irate(node_cpu_seconds_total{mode="idle"}[5m])) * 100) > 80
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "CPU usage is above 80%"
          description: "CPU usage is {{ $value }}% on {{ $labels.instance }}"

      # Mémoire faible
      - alert: LowMemory
        expr: 100 * (1 - ((node_memory_MemAvailable_bytes) / (node_memory_MemTotal_bytes))) > 90
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "Memory usage is above 90%"
          description: "Memory usage is {{ $value }}% on {{ $labels.instance }}"

      # Espace disque faible
      - alert: LowDiskSpace
        expr: (node_filesystem_avail_bytes{mountpoint="/"} / node_filesystem_size_bytes{mountpoint="/"}) * 100 < 10
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "Disk space is below 10%"
          description: "Only {{ $value }}% disk space available"

      # Base de données - trop de connexions
      - alert: TooManyDatabaseConnections
        expr: pg_stat_database_numbackends > 80
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Too many database connections"
          description: "{{ $value }} connections to {{ $labels.datname }}"

      # Conteneur arrêté
      - alert: ContainerDown
        expr: time() - container_last_seen{name=~"keyhome-.+"} > 60
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "Container {{ $labels.name }} is down"
          description: "Container has been down for more than 1 minute"
```

Puis modifiez `.docker/monitoring/prometheus/prometheus.yml` :

```yaml
rule_files:
  - "alerts.yml"

alerting:
  alertmanagers:
    - static_configs:
        - targets:
            - alertmanager:9093  # Si vous utilisez Alertmanager
```

---

## 📊 Dashboards recommandés à créer

### 1. **Application Dashboard Laravel**

Panels à inclure :
- Nombre total d'annonces
- Utilisateurs actifs aujourd'hui
- Nouvelles inscriptions (jour/semaine/mois)
- Requêtes API par endpoint (top 10)
- Temps de réponse moyen des APIs
- Erreurs 500 par heure

### 2. **Database Performance**

Panels à inclure :
- Connexions actives
- Requêtes lentes (> 1s)
- Cache hit ratio
- Taille de la base de données (évolution)
- Transactions par seconde
- Deadlocks détectés

### 3. **Infrastructure Health**

Panels à inclure :
- CPU, RAM, Disk de chaque conteneur
- Trafic réseau in/out
- Uptime de chaque service
- Logs d'erreurs récents

---

## 🎯 Métriques métier Laravel (bonus)

Pour ajouter vos propres métriques Laravel, installez un package Prometheus :

```bash
composer require promphp/prometheus_client_php
composer require arquivei/laravel-prometheus-exporter
```

Puis exposez des métriques :

```php
// Dans un Controller ou Job
use Prometheus\CollectorRegistry;

public function trackAdCreation()
{
    $registry = app(CollectorRegistry::class);
    $counter = $registry->getOrRegisterCounter(
        'app',
        'ads_created_total',
        'Total number of ads created'
    );
    $counter->inc();
}
```

---

## 🔧 Maintenance

### Nettoyer les anciennes données

```bash
# Prometheus conserve 15 jours par défaut
# Pour changer la rétention, modifiez dans docker-compose.yml :
command:
  - '--storage.tsdb.retention.time=30d'  # 30 jours
```

### Backup de Grafana

```bash
# Exporter tous les dashboards
docker-compose exec grafana grafana-cli admin export

# Backup du volume
docker-compose exec grafana tar -czf /tmp/grafana-backup.tar.gz /var/lib/grafana
docker cp keyhome-grafana:/tmp/grafana-backup.tar.gz ./grafana-backup.tar.gz
```

---

## 🆘 Dépannage

### Prometheus ne collecte pas de données

```bash
# Vérifiez les targets
# Accédez à http://localhost:9090/targets
# Tous les targets doivent être "UP"

# Vérifiez les logs
docker-compose logs prometheus
```

### Grafana ne se connecte pas à Prometheus

```bash
# Vérifiez la connexion réseau
docker-compose exec grafana curl http://keyhome-prometheus:9090/api/v1/query?query=up

# Recréez la data source
# Dans Grafana: Configuration → Data Sources → Prometheus → Test
```

### Métriques manquantes

```bash
# Vérifiez que les exporters fonctionnent
docker-compose ps node-exporter cadvisor postgres-exporter redis-exporter

# Testez manuellement un exporter
curl http://localhost:9100/metrics  # Node Exporter
curl http://localhost:8080/metrics  # cAdvisor
```

---

## 📚 Ressources

- [Documentation Grafana](https://grafana.com/docs/)
- [Documentation Prometheus](https://prometheus.io/docs/)
- [PromQL Cheat Sheet](https://promlabs.com/promql-cheat-sheet/)
- [Grafana Dashboards Library](https://grafana.com/grafana/dashboards/)
- [Awesome Prometheus](https://github.com/roaldnefs/awesome-prometheus)

---

## ✅ Checklist de démarrage

- [ ] Grafana accessible et changement du mot de passe admin
- [ ] Data source Prometheus configurée et testée
- [ ] Dashboard "Docker & System Monitoring" visible
- [ ] Importé dashboard ID 1860 (Node Exporter Full)
- [ ] Toutes les targets Prometheus sont "UP"
- [ ] Configuration d'au moins une alerte critique
- [ ] Backup automatique configuré (optionnel)

---

**Bon monitoring! 📊🚀**
