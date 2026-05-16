#!/usr/bin/env bash
# =============================================================================
#  kh — KeyHome DevOps CLI
#  Script de gestion complète de l'infrastructure VPS KeyHome
# =============================================================================
#
#  SYNOPSIS
#    kh <COMMANDE> [SOUS-COMMANDE] [OPTIONS]
#
#  DESCRIPTION
#    Outil de gestion unifié pour les environnements prod et preprod de KeyHome.
#    Couvre : containers, images, logs, base de données, réseau, monitoring,
#    déploiement, nettoyage et diagnostics.
#
#  INSTALLATION (sur le VPS)
#    sudo cp /opt/keyhome/scripts/kh.sh /usr/local/bin/kh
#    sudo chmod +x /usr/local/bin/kh
#
#  USAGE RAPIDE
#    kh status             # Vue d'ensemble de tous les containers
#    kh logs app           # Logs du container app (prod)
#    kh restart web        # Redémarrer le nginx prod
#    kh db shell           # Shell psql sur la prod
#    kh db clone           # Copier prod → preprod
#    kh monitor            # Dashboard temps réel (stats CPU/RAM)
#    kh clean              # Nettoyage Docker (images, containers, cache)
#    kh health             # Healthcheck complet prod + preprod
#    kh deploy prod        # Redéployer prod (pull dernière image)
#    kh deploy preprod     # Redéployer preprod
#
# =============================================================================
set -euo pipefail
IFS=$'\n\t'

# ─────────────────────────────────────────────────────────────────────────────
#  CONFIGURATION
# ─────────────────────────────────────────────────────────────────────────────
readonly PROD_DIR="/opt/keyhome"
readonly PREPROD_DIR="/opt/keyhome-preprod"
readonly PROD_PREFIX="keyhome-prod"
readonly PREPROD_PREFIX="keyhome-preprod"
readonly PROD_DB_CONTAINER="keyhome-prod-db"
readonly PROD_DB=$(grep -E "^DB_DATABASE=" "$PROD_DIR/.env" 2>/dev/null | cut -d= -f2 | tr -d "[:space:]'\"" || echo "keyhome_prod")
readonly PREPROD_DB=$(grep -E "^DB_DATABASE=" "$PREPROD_DIR/.env" 2>/dev/null | cut -d= -f2 | tr -d "[:space:]'\"" || echo "keyhome_preprod")
readonly DB_USER=$(grep -E "^DB_USERNAME=" "$PROD_DIR/.env" 2>/dev/null | cut -d= -f2 | tr -d "[:space:]'\"" || echo "cedrick")
readonly DB_PASS=$(grep -E "^DB_PASSWORD=" "$PROD_DIR/.env" 2>/dev/null | cut -d= -f2 | tr -d "[:space:]'\"" || echo "")
readonly REGISTRY="registry.gitlab.com/neocraft/projets/keyhome/backend/app"
readonly DISK_WARN=70
readonly DISK_CRIT=85
readonly VERSION="2.0.0"

# ─────────────────────────────────────────────────────────────────────────────
#  COULEURS & FORMATAGE
# ─────────────────────────────────────────────────────────────────────────────
if [[ -t 1 ]]; then
  RED='\033[0;31m'; YELLOW='\033[1;33m'; GREEN='\033[0;32m'
  BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'
  MAGENTA='\033[0;35m'; DIM='\033[2m'
else
  RED=''; YELLOW=''; GREEN=''; BLUE=''; CYAN=''; BOLD=''; RESET=''; MAGENTA=''; DIM=''
fi

_header()  { echo -e "\n${BOLD}${CYAN}══════════════════════════════════════════${RESET}"; echo -e "${BOLD}${CYAN}  $*${RESET}"; echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"; }
_section() { echo -e "\n${BOLD}${BLUE}▸ $*${RESET}"; }
_ok()      { echo -e "  ${GREEN}✓${RESET} $*"; }
_warn()    { echo -e "  ${YELLOW}⚠${RESET}  $*"; }
_err()     { echo -e "  ${RED}✗${RESET} $*" >&2; }
_info()    { echo -e "  ${DIM}→${RESET} $*"; }
_die()     { _err "$*"; exit 1; }

_confirm() {
  local msg="${1:-Confirmer ?}"
  echo -e "${YELLOW}  ⚠  ${msg}${RESET}"
  read -r -p "  Taper 'yes' pour confirmer : " ans
  [[ "$ans" == "yes" ]] || { echo "  Annulé."; exit 0; }
}

_env_flag() {
  # Retourne "prod" ou "preprod" selon --env flag, défaut prod
  local env="prod"
  for arg in "$@"; do [[ "$arg" == "--preprod" || "$arg" == "-p" ]] && env="preprod"; done
  echo "$env"
}

_compose_dir() { [[ "$1" == "preprod" ]] && echo "$PREPROD_DIR" || echo "$PROD_DIR"; }
_prefix()       { [[ "$1" == "preprod" ]] && echo "$PREPROD_PREFIX" || echo "$PROD_PREFIX"; }
_app_svc()      { [[ "$1" == "preprod" ]] && echo "backend" || echo "app"; }
_db_name()      { [[ "$1" == "preprod" ]] && echo "$PREPROD_DB" || echo "$PROD_DB"; }

_psql() {
  # Usage: _psql -d <db> -c "<sql>"
  PGPASSWORD="$DB_PASS" docker exec -i "$PROD_DB_CONTAINER" \
    psql -U "$DB_USER" "$@"
}

# ─────────────────────────────────────────────────────────────────────────────
#  AIDE
# ─────────────────────────────────────────────────────────────────────────────
_usage() {
cat << EOF
${BOLD}kh ${VERSION}${RESET} — KeyHome DevOps CLI

${BOLD}USAGE${RESET}
  kh <commande> [sous-commande] [--preprod|-p] [options]

${BOLD}COMMANDES CONTAINERS${RESET}
  ${CYAN}status${RESET}  [--preprod]           Vue d'ensemble de tous les containers
  ${CYAN}ps${RESET}      [--preprod]           Liste courte (docker ps style)
  ${CYAN}top${RESET}     [--preprod]           CPU / RAM en temps réel (docker stats)
  ${CYAN}restart${RESET} <service> [--preprod] Redémarrer un container (ex: web, app, worker)
  ${CYAN}stop${RESET}    <service> [--preprod] Arrêter un container
  ${CYAN}start${RESET}   <service> [--preprod] Démarrer un container
  ${CYAN}exec${RESET}    <service> <cmd>       Executer une commande dans un container
  ${CYAN}shell${RESET}   <service> [--preprod] Shell interactif dans un container

${BOLD}COMMANDES LOGS${RESET}
  ${CYAN}logs${RESET}    <service> [--preprod] [-n <N>] [-f] [--errors]
                          Afficher les logs (défaut: 100 lignes)
                          --errors : filtrer uniquement les erreurs
  ${CYAN}errors${RESET}  [--preprod]           Toutes les erreurs des 30 dernières minutes

${BOLD}COMMANDES BASE DE DONNÉES${RESET}
  ${CYAN}db shell${RESET}   [--preprod]        Shell psql interactif
  ${CYAN}db query${RESET}   <sql>              Exécuter une requête SQL (prod)
  ${CYAN}db clone${RESET}                      Copier prod → preprod (avec confirmation)
  ${CYAN}db flush${RESET}                      Vider preprod + remigrer (avec confirmation)
  ${CYAN}db status${RESET}                     Comparer lignes par table prod vs preprod
  ${CYAN}db backup${RESET}  [fichier.dump]     Dump de la prod (format pg_dump custom)
  ${CYAN}db restore${RESET} <fichier.dump>     Restaurer un dump sur preprod
  ${CYAN}db size${RESET}                       Taille des bases et tables

${BOLD}COMMANDES IMAGES & REGISTRY${RESET}
  ${CYAN}images${RESET}                        Lister toutes les images keyhome sur le VPS
  ${CYAN}pull${RESET}    [prod|preprod|all]    Tirer la dernière image depuis le registry
  ${CYAN}rmi${RESET}     [dangling|old|all]   Supprimer des images (avec confirmation)
  ${CYAN}inspect${RESET} <service>             Inspecter une image (layers, env, labels)

${BOLD}COMMANDES DÉPLOIEMENT${RESET}
  ${CYAN}deploy${RESET}  <prod|preprod>        Déployer la dernière image (pull + recreate)
  ${CYAN}rollback${RESET} <prod|preprod>       Revenir à l'image précédente
  ${CYAN}migrate${RESET} [--preprod]           Lancer les migrations Laravel
  ${CYAN}artisan${RESET} <cmd> [--preprod]     Exécuter une commande artisan

${BOLD}COMMANDES MONITORING${RESET}
  ${CYAN}health${RESET}  [--preprod]           Healthcheck complet de l'environnement
  ${CYAN}monitor${RESET}                       Dashboard temps réel (stats toutes 2s)
  ${CYAN}disk${RESET}                          Utilisation disque (VPS + Docker)
  ${CYAN}network${RESET}                       Topologie réseau Docker
  ${CYAN}fpm${RESET}                           Statut et stats PHP-FPM

${BOLD}COMMANDES NETTOYAGE${RESET}
  ${CYAN}clean${RESET}   [--dry-run]           Nettoyer images/containers/cache inutilisés
  ${CYAN}clean volumes${RESET} [--dry-run]     Nettoyer les volumes orphelins
  ${CYAN}clean all${RESET}                     Nettoyage complet (dangereux — confirmation)
  ${CYAN}prune${RESET}                         docker system prune -f (images+containers)

${BOLD}OPTIONS GLOBALES${RESET}
  ${CYAN}--preprod${RESET} | ${CYAN}-p${RESET}            Opérer sur l'environnement preprod
  ${CYAN}--help${RESET}   | ${CYAN}-h${RESET}             Afficher cette aide
  ${CYAN}--version${RESET}                  Version du script

${BOLD}EXEMPLES${RESET}
  kh status                         # État de la prod
  kh status --preprod               # État de la preprod
  kh logs app -f                    # Suivre les logs FPM en direct
  kh logs worker --errors           # Uniquement les erreurs du worker
  kh restart web                    # Redémarrer nginx prod
  kh exec app "php artisan cache:clear"
  kh artisan "queue:restart" --preprod
  kh db clone                       # Copier prod → preprod
  kh db flush                       # Vider et remigrée la preprod
  kh deploy prod                    # Déployer la prod (dernière image)
  kh health                         # Audit complet prod
  kh monitor                        # Dashboard temps réel
  kh clean --dry-run                # Voir ce qui serait nettoyé
  kh disk                           # Utilisation disque

EOF
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : status
# ─────────────────────────────────────────────────────────────────────────────
cmd_status() {
  local env; env=$(_env_flag "$@")
  local prefix; prefix=$(_prefix "$env")
  _header "STATUS — ${env^^}"

  _section "Containers"
  printf "  %-42s %-20s %-15s %-15s\n" "NOM" "STATUT" "CPU" "RAM"
  echo "  $(printf '%.0s─' {1..90})"
  docker stats --no-stream --format \
    "{{.Name}}\t{{.Status}}\t{{.CPUPerc}}\t{{.MemUsage}}" 2>/dev/null | \
    grep "^${prefix}" | sort | \
    while IFS=$'\t' read -r name status cpu mem; do
      local color="$GREEN"
      [[ "$status" == *"unhealthy"* ]] && color="$RED"
      [[ "$status" == *"starting"* ]] && color="$YELLOW"
      [[ ! "$status" == *"healthy"* && ! "$status" == *"Up"* ]] && color="$RED"
      printf "  %-42s ${color}%-20s${RESET} %-15s %-15s\n" "$name" "${status:0:20}" "$cpu" "${mem:0:20}"
    done

  _section "Volumes"
  docker volume ls --format "{{.Name}}" | grep "^keyhome" | grep -E "$(echo "$prefix" | sed 's/keyhome-//')" | \
    while read -r vol; do
      local size; size=$(docker run --rm -v "$vol":/v alpine sh -c "du -sh /v 2>/dev/null | cut -f1" 2>/dev/null || echo "?")
      _info "$vol (${size})"
    done

  _section "Réseau"
  if [[ "$env" == "prod" ]]; then
    _info "keyhome_keyhome-network (172.25.2.0/24)"
    _info "traefik-public (172.25.1.0/24)"
  else
    _info "keyhome-preprod_preprod-network (172.25.4.0/24)"
  fi

  _section "Disque VPS"
  local used; used=$(df /var/lib/docker | awk 'NR==2{print $5}' | tr -d '%')
  local avail; avail=$(df -h /var/lib/docker | awk 'NR==2{print $4}')
  if   [[ "$used" -ge "$DISK_CRIT" ]]; then _err "Disque : ${used}% utilisé — CRITIQUE (${avail} libre)"
  elif [[ "$used" -ge "$DISK_WARN" ]]; then _warn "Disque : ${used}% utilisé — ATTENTION (${avail} libre)"
  else _ok "Disque : ${used}% utilisé (${avail} libre)"
  fi
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : ps
# ─────────────────────────────────────────────────────────────────────────────
cmd_ps() {
  local env; env=$(_env_flag "$@")
  local prefix; prefix=$(_prefix "$env")
  docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Image}}" | \
    { head -1; grep "^${prefix}" | sort; }
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : top
# ─────────────────────────────────────────────────────────────────────────────
cmd_top() {
  local env; env=$(_env_flag "$@")
  local prefix; prefix=$(_prefix "$env")
  local filter=""; for c in $(docker ps --format "{{.Names}}" | grep "^${prefix}"); do filter="$filter $c"; done
  docker stats $filter
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : restart / stop / start
# ─────────────────────────────────────────────────────────────────────────────
cmd_restart() {
  local svc="${1:-}"; [[ -z "$svc" ]] && _die "Usage: kh restart <service> [--preprod]"
  local env; env=$(_env_flag "$@")
  local dir; dir=$(_compose_dir "$env")
  local full="${_prefix "$env"}-${svc}"
  _info "Redémarrage de ${full}..."
  cd "$dir" && docker compose restart "$svc"
  _ok "$full redémarré"
}

cmd_stop() {
  local svc="${1:-}"; [[ -z "$svc" ]] && _die "Usage: kh stop <service> [--preprod]"
  local env; env=$(_env_flag "$@")
  local dir; dir=$(_compose_dir "$env")
  cd "$dir" && docker compose stop "$svc"
  _ok "$svc arrêté (${env})"
}

cmd_start() {
  local svc="${1:-}"; [[ -z "$svc" ]] && _die "Usage: kh start <service> [--preprod]"
  local env; env=$(_env_flag "$@")
  local dir; dir=$(_compose_dir "$env")
  cd "$dir" && docker compose start "$svc"
  _ok "$svc démarré (${env})"
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : exec / shell
# ─────────────────────────────────────────────────────────────────────────────
cmd_exec() {
  local svc="${1:-}"; shift || true
  local env; env=$(_env_flag "$@")
  local prefix; prefix=$(_prefix "$env")
  local container="${prefix}-${svc}"
  [[ -z "$svc" ]] && _die "Usage: kh exec <service> <commande>"
  # Retire les flags kh de la liste d'arguments
  local args=(); for a in "$@"; do [[ "$a" != "--preprod" && "$a" != "-p" ]] && args+=("$a"); done
  docker exec "$container" "${args[@]}"
}

cmd_shell() {
  local svc="${1:-app}"
  local env; env=$(_env_flag "$@")
  local prefix; prefix=$(_prefix "$env")
  [[ "$env" == "preprod" ]] && svc="${svc/app/backend}"
  local container="${prefix}-${svc}"
  _info "Shell interactif dans ${container}..."
  docker exec -it "$container" sh 2>/dev/null || docker exec -it "$container" bash
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : logs
# ─────────────────────────────────────────────────────────────────────────────
cmd_logs() {
  local svc="${1:-app}"; shift || true
  local env="prod"; local follow=""; local lines=100; local errors_only=false
  local since=""; local prefix

  # Parser les options
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --preprod|-p) env="preprod" ;;
      -f|--follow)  follow="-f" ;;
      -n)           shift; lines="${1:-100}" ;;
      --errors)     errors_only=true ;;
      --since)      shift; since="--since ${1}" ;;
      *) ;;
    esac
    shift
  done

  prefix=$(_prefix "$env")
  [[ "$env" == "preprod" && "$svc" == "app" ]] && svc="backend"
  local container="${prefix}-${svc}"

  if $errors_only; then
    docker logs --tail "$lines" $since $follow "$container" 2>&1 | \
      grep -iE "error|fatal|exception|critical|WARN|WARNING" | \
      grep -v "INFO\|DEBUG\|healthcheck"
  else
    docker logs --tail "$lines" $since $follow "$container" 2>&1
  fi
}

cmd_errors() {
  local env; env=$(_env_flag "$@")
  local prefix; prefix=$(_prefix "$env")
  _header "ERREURS — ${env^^} (30 dernières minutes)"
  for c in $(docker ps --format "{{.Names}}" | grep "^${prefix}" | sort); do
    local errs; errs=$(docker logs --since 30m "$c" 2>&1 | grep -iE "error|fatal|exception|critical" | grep -v "INFO\|DEBUG\|healthcheck" | head -5)
    if [[ -n "$errs" ]]; then
      _section "$c"
      echo "$errs" | while read -r l; do echo -e "    ${RED}$l${RESET}"; done
    fi
  done
  _ok "Analyse terminée"
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : db
# ─────────────────────────────────────────────────────────────────────────────
cmd_db() {
  local sub="${1:-status}"; shift || true

  case "$sub" in
    shell)
      local env; env=$(_env_flag "$@")
      local db; db=$(_db_name "$env")
      _info "Connexion psql → $db"
      PGPASSWORD="$DB_PASS" docker exec -it "$PROD_DB_CONTAINER" psql -U "$DB_USER" -d "$db"
      ;;

    query)
      local sql="${1:-}"; [[ -z "$sql" ]] && _die "Usage: kh db query <sql>"
      _psql -d "$PROD_DB" -c "$sql"
      ;;

    clone)
      _header "CLONE PROD → PREPROD"
      _confirm "Cela va ÉCRASER toutes les données de $PREPROD_DB avec celles de $PROD_DB."
      local dump="/tmp/kh_prod_clone_$(date +%Y%m%d_%H%M%S).dump"
      _info "Dump de $PROD_DB..."
      PGPASSWORD="$DB_PASS" docker exec "$PROD_DB_CONTAINER" \
        pg_dump -U "$DB_USER" -Fc --no-owner --no-acl "$PROD_DB" > "$dump"
      _ok "Dump: $dump ($(du -sh "$dump" | cut -f1))"

      _info "DROP + CREATE $PREPROD_DB..."
      _psql -d postgres -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='$PREPROD_DB' AND pid <> pg_backend_pid();" >/dev/null 2>&1 || true
      _psql -d postgres -c "DROP DATABASE IF EXISTS \"$PREPROD_DB\";" >/dev/null
      _psql -d postgres -c "CREATE DATABASE \"$PREPROD_DB\" OWNER \"$DB_USER\";" >/dev/null
      _ok "$PREPROD_DB recréé"

      _info "Restore vers $PREPROD_DB..."
      PGPASSWORD="$DB_PASS" docker exec -i "$PROD_DB_CONTAINER" \
        pg_restore -U "$DB_USER" -d "$PREPROD_DB" --no-owner --no-acl < "$dump" 2>/dev/null || true
      _ok "Restore terminé"

      _info "Migrations preprod..."
      docker exec "keyhome-preprod-backend" php artisan migrate --force 2>/dev/null || true
      rm -f "$dump"
      _ok "Preprod synchronisée avec la prod !"
      ;;

    flush)
      _header "FLUSH PREPROD"
      _confirm "Cela va SUPPRIMER toutes les données de $PREPROD_DB et relancer les migrations."
      _psql -d postgres -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='$PREPROD_DB' AND pid <> pg_backend_pid();" >/dev/null 2>&1 || true
      _psql -d postgres -c "DROP DATABASE IF EXISTS \"$PREPROD_DB\";" >/dev/null
      _psql -d postgres -c "CREATE DATABASE \"$PREPROD_DB\" OWNER \"$DB_USER\";" >/dev/null
      _psql -d "$PREPROD_DB" -c "CREATE EXTENSION IF NOT EXISTS postgis; CREATE EXTENSION IF NOT EXISTS postgis_topology;" >/dev/null 2>&1 || true
      _ok "$PREPROD_DB recréé (vide)"
      _info "Migrations + seeders..."
      docker exec "keyhome-preprod-backend" php artisan migrate --force
      docker exec "keyhome-preprod-backend" php artisan db:seed --class=DatabaseSeeder --force 2>/dev/null || true
      _ok "Preprod vidée et réinitialisée"
      ;;

    backup)
      local file="${1:-/opt/keyhome/backups/prod_$(date +%Y%m%d_%H%M%S).dump}"
      mkdir -p "$(dirname "$file")"
      _info "Backup de $PROD_DB → $file"
      PGPASSWORD="$DB_PASS" docker exec "$PROD_DB_CONTAINER" \
        pg_dump -U "$DB_USER" -Fc --no-owner --no-acl "$PROD_DB" > "$file"
      _ok "Backup terminé : $file ($(du -sh "$file" | cut -f1))"
      ;;

    restore)
      local file="${1:-}"; [[ -z "$file" || ! -f "$file" ]] && _die "Usage: kh db restore <fichier.dump>"
      _confirm "Cela va restaurer $file sur $PREPROD_DB."
      PGPASSWORD="$DB_PASS" docker exec -i "$PROD_DB_CONTAINER" \
        pg_restore -U "$DB_USER" -d "$PREPROD_DB" --no-owner --no-acl --clean < "$file" 2>/dev/null || true
      _ok "Restore terminé sur $PREPROD_DB"
      ;;

    status)
      _header "DB STATUS"
      _section "Bases disponibles"
      _psql -d postgres -c "\l" | grep -E "keyhome|Name|\-\-\-" | head -10

      _section "Taille des bases"
      _psql -d postgres -c "SELECT datname, pg_size_pretty(pg_database_size(datname)) AS taille FROM pg_database WHERE datname LIKE 'keyhome%' ORDER BY pg_database_size(datname) DESC;"

      _section "Top 10 tables prod (lignes)"
      _psql -d "$PROD_DB" -c "SELECT tablename, n_live_tup AS lignes FROM pg_stat_user_tables ORDER BY n_live_tup DESC LIMIT 10;"
      ;;

    size)
      _section "Taille des tables (prod)"
      _psql -d "$PROD_DB" -c "SELECT tablename, pg_size_pretty(pg_total_relation_size(tablename::regclass)) AS taille, n_live_tup AS lignes FROM pg_stat_user_tables ORDER BY pg_total_relation_size(tablename::regclass) DESC LIMIT 20;"
      ;;

    *) _die "Sous-commande db inconnue : $sub (shell|query|clone|flush|backup|restore|status|size)" ;;
  esac
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : images
# ─────────────────────────────────────────────────────────────────────────────
cmd_images() {
  _header "IMAGES KEYHOME"
  docker images --format "table {{.Repository}}:{{.Tag}}\t{{.CreatedSince}}\t{{.Size}}" | \
    { head -1; grep keyhome | sort; }
  echo ""
  _section "Utilisation Docker totale"
  docker system df
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : pull
# ─────────────────────────────────────────────────────────────────────────────
cmd_pull() {
  local target="${1:-all}"
  case "$target" in
    prod|main)    _info "Pull app:main...";    docker pull "${REGISTRY}:main";    _ok "app:main mis à jour" ;;
    preprod)      _info "Pull app:preprod..."; docker pull "${REGISTRY}:preprod"; _ok "app:preprod mis à jour" ;;
    all)
      _info "Pull app:main...";    docker pull "${REGISTRY}:main"
      _info "Pull app:preprod..."; docker pull "${REGISTRY}:preprod"
      _ok "Images mises à jour"
      ;;
    *) _die "Usage: kh pull [prod|preprod|all]" ;;
  esac
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : rmi
# ─────────────────────────────────────────────────────────────────────────────
cmd_rmi() {
  local target="${1:-dangling}"
  case "$target" in
    dangling)
      local imgs; imgs=$(docker images -f "dangling=true" -q 2>/dev/null)
      if [[ -z "$imgs" ]]; then _ok "Aucune image dangling"; return; fi
      local count; count=$(echo "$imgs" | wc -l | tr -d ' ')
      _confirm "Supprimer $count images dangling ?"
      docker rmi $imgs 2>/dev/null || true
      _ok "$count images dangling supprimées"
      ;;
    old)
      _info "Images keyhome (hors main/preprod/latest) :"
      docker images --format "{{.Repository}}:{{.Tag}}\t{{.ID}}" | grep keyhome | \
        grep -Ev ":main|:preprod|:latest" | head -20
      _confirm "Supprimer ces images ?"
      docker images --format "{{.ID}}" | xargs docker rmi 2>/dev/null || true
      _ok "Vieilles images supprimées"
      ;;
    *) _die "Usage: kh rmi [dangling|old]" ;;
  esac
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : inspect
# ─────────────────────────────────────────────────────────────────────────────
cmd_inspect() {
  local svc="${1:-app}"
  local env; env=$(_env_flag "$@")
  local prefix; prefix=$(_prefix "$env")
  [[ "$env" == "preprod" && "$svc" == "app" ]] && svc="backend"
  local container="${prefix}-${svc}"
  _header "INSPECT — $container"

  _section "Container"
  docker inspect "$container" --format \
    "Image: {{.Config.Image}}
Démarré: {{.State.StartedAt}}
PID: {{.State.Pid}}
RestartCount: {{.RestartCount}}" 2>/dev/null

  _section "Environment (filtré)"
  docker inspect "$container" --format '{{range .Config.Env}}{{println .}}{{end}}' 2>/dev/null | \
    grep -Ev "PASSWORD|SECRET|KEY|TOKEN" | sort

  _section "Ports"
  docker inspect "$container" --format '{{range $p,$b := .NetworkSettings.Ports}}{{$p}} → {{range $b}}{{.HostIp}}:{{.HostPort}}{{end}}{{"\n"}}{{end}}' 2>/dev/null

  _section "Volumes montés"
  docker inspect "$container" --format '{{range .Mounts}}{{.Type}}: {{.Source}} → {{.Destination}}{{"\n"}}{{end}}' 2>/dev/null
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : deploy
# ─────────────────────────────────────────────────────────────────────────────
cmd_deploy() {
  local env="${1:-prod}"; [[ "$env" != "prod" && "$env" != "preprod" ]] && _die "Usage: kh deploy <prod|preprod>"
  local dir; dir=$(_compose_dir "$env")
  local tag; [[ "$env" == "prod" ]] && tag="main" || tag="preprod"
  local app_svc; app_svc=$(_app_svc "$env")

  _header "DEPLOY — ${env^^}"
  _info "Pull ${REGISTRY}:${tag}..."
  docker pull "${REGISTRY}:${tag}"

  _info "Mise à jour des containers (app, worker, reverb)..."
  cd "$dir"
  APP_IMAGE="${REGISTRY}:${tag}" docker compose up -d --no-build \
    --no-deps --force-recreate "$app_svc" worker reverb 2>/dev/null || \
  APP_IMAGE="${REGISTRY}:${tag}" docker compose up -d --no-build \
    --no-deps --force-recreate "$app_svc" worker 2>/dev/null

  _info "Migrations..."
  sleep 5
  docker exec "${_prefix "$env"}-${app_svc}" php artisan migrate --force || _warn "Migrations échouées — vérifier les logs"

  _ok "Déploiement ${env} terminé !"
  cmd_ps "$env"
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : migrate / artisan
# ─────────────────────────────────────────────────────────────────────────────
cmd_migrate() {
  local env; env=$(_env_flag "$@")
  local app_svc; app_svc=$(_app_svc "$env")
  local container="${_prefix "$env"}-${app_svc}"
  _info "Migrations sur $container..."
  docker exec "$container" php artisan migrate --force
  _ok "Migrations terminées"
}

cmd_artisan() {
  local env; env=$(_env_flag "$@")
  local app_svc; app_svc=$(_app_svc "$env")
  local container="${_prefix "$env"}-${app_svc}"
  # Filtrer les flags kh
  local args=(); for a in "$@"; do [[ "$a" != "--preprod" && "$a" != "-p" ]] && args+=("$a"); done
  [[ ${#args[@]} -eq 0 ]] && _die "Usage: kh artisan <commande> [--preprod]"
  docker exec "$container" php artisan "${args[@]}"
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : health
# ─────────────────────────────────────────────────────────────────────────────
cmd_health() {
  local env; env=$(_env_flag "$@")
  local prefix; prefix=$(_prefix "$env")
  local app_svc; app_svc=$(_app_svc "$env")
  local web_container="${prefix}-web"
  local app_container="${prefix}-${app_svc}"

  _header "HEALTHCHECK — ${env^^}"
  local ok=0; local warn=0; local fail=0

  # Containers
  _section "Containers"
  for c in $(docker ps -a --format "{{.Names}}" | grep "^${prefix}" | sort); do
    local status; status=$(docker inspect "$c" --format "{{.State.Status}}" 2>/dev/null)
    local health; health=$(docker inspect "$c" --format "{{.State.Health.Status}}" 2>/dev/null || echo "none")
    if   [[ "$status" == "running" && ("$health" == "healthy" || "$health" == "none") ]]; then
      _ok "$c ($health)"; ((ok++)) || true
    elif [[ "$status" == "running" && "$health" == "starting" ]]; then
      _warn "$c (démarrage...)"; ((warn++)) || true
    else
      _err "$c — status=$status health=$health"; ((fail++)) || true
    fi
  done

  # API response
  _section "API Laravel"
  local api_code; api_code=$(docker exec "$web_container" wget -qO- --server-response http://localhost/up 2>&1 | head -1 | awk '{print $2}' || echo "0")
  if [[ "$api_code" == "200" ]]; then _ok "GET /up → 200 OK"; ((ok++)) || true
  else _err "GET /up → $api_code"; ((fail++)) || true
  fi

  # FPM workers
  _section "PHP-FPM"
  local workers; workers=$(docker exec "$app_container" ps aux 2>/dev/null | grep "php-fpm: pool" | grep -v grep | wc -l || echo "0")
  local max_children; max_children=$(docker exec "$app_container" sh -c "grep '^pm.max_children' /usr/local/etc/php-fpm.d/www.conf | cut -d= -f2 | tr -d ' '" 2>/dev/null || echo "?")
  _ok "$workers workers actifs / $max_children max"

  # Redis
  _section "Redis"
  local redis_pong; redis_pong=$(docker exec "${prefix}-redis" redis-cli ping 2>/dev/null || echo "FAIL")
  [[ "$redis_pong" == "PONG" ]] && { _ok "Redis PONG"; ((ok++)) || true; } || { _err "Redis: $redis_pong"; ((fail++)) || true; }

  # DB
  _section "PostgreSQL"
  local pg_ready; pg_ready=$(docker exec "$PROD_DB_CONTAINER" pg_isready -U "$DB_USER" 2>/dev/null | grep -c "accepting" || echo "0")
  [[ "$pg_ready" -ge 1 ]] && { _ok "PostgreSQL accepte les connexions"; ((ok++)) || true; } || { _err "PostgreSQL non disponible"; ((fail++)) || true; }

  # Disk
  _section "Disque"
  local disk_used; disk_used=$(df /var/lib/docker | awk 'NR==2{print $5}' | tr -d '%')
  local disk_avail; disk_avail=$(df -h /var/lib/docker | awk 'NR==2{print $4}')
  if   [[ "$disk_used" -ge "$DISK_CRIT" ]]; then _err "Disque : ${disk_used}% (${disk_avail} libre)"; ((fail++)) || true
  elif [[ "$disk_used" -ge "$DISK_WARN" ]]; then _warn "Disque : ${disk_used}% (${disk_avail} libre)"; ((warn++)) || true
  else _ok "Disque : ${disk_used}% (${disk_avail} libre)"; ((ok++)) || true
  fi

  # Résumé
  echo ""
  echo -e "  ${BOLD}Résumé :${RESET} ${GREEN}${ok} OK${RESET}  ${YELLOW}${warn} WARN${RESET}  ${RED}${fail} FAIL${RESET}"
  [[ "$fail" -gt 0 ]] && exit 1 || true
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : monitor
# ─────────────────────────────────────────────────────────────────────────────
cmd_monitor() {
  _header "MONITOR TEMPS RÉEL (Ctrl+C pour quitter)"
  _info "Actualisation toutes les 2 secondes"
  docker stats --format \
    "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}\t{{.NetIO}}\t{{.BlockIO}}" \
    $(docker ps --format "{{.Names}}" | grep keyhome | sort | tr '\n' ' ')
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : disk
# ─────────────────────────────────────────────────────────────────────────────
cmd_disk() {
  _header "UTILISATION DISQUE"

  _section "VPS global"
  df -h / /var/lib/docker 2>/dev/null | awk 'NR==1 || NR>1' | column -t

  _section "Docker (images + volumes + cache)"
  docker system df -v 2>/dev/null

  _section "Top 5 volumes par taille"
  docker volume ls -q | grep keyhome | while read -r vol; do
    local mnt; mnt=$(docker volume inspect "$vol" --format "{{.Mountpoint}}" 2>/dev/null)
    [[ -d "$mnt" ]] && echo "$(du -sh "$mnt" 2>/dev/null | cut -f1)\t$vol" || true
  done | sort -rh | head -5 | while IFS=$'\t' read -r size vol; do
    _info "$size  →  $vol"
  done

  _section "Top 5 images par taille"
  docker images --format "{{.Size}}\t{{.Repository}}:{{.Tag}}" | sort -rh | head -5 | \
    while IFS=$'\t' read -r size img; do _info "$size  →  $img"; done
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : network
# ─────────────────────────────────────────────────────────────────────────────
cmd_network() {
  _header "TOPOLOGIE RÉSEAU"
  for net in keyhome_keyhome-network keyhome-preprod_preprod-network traefik-public; do
    _section "$net"
    docker network inspect "$net" 2>/dev/null | \
      python3 -c "
import json,sys
data=json.load(sys.stdin)[0]
subnet=data.get('IPAM',{}).get('Config',[{}])[0].get('Subnet','?')
print(f'  Subnet: {subnet}')
for cid,c in data.get('Containers',{}).items():
    print(f'  {c[\"Name\"]:<45} {c[\"IPv4Address\"]}')
" 2>/dev/null || _warn "Réseau non disponible"
  done
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : fpm
# ─────────────────────────────────────────────────────────────────────────────
cmd_fpm() {
  _header "PHP-FPM STATUS"
  _section "Configuration"
  docker exec keyhome-prod-app sh -c "grep -E '^pm' /usr/local/etc/php-fpm.d/www.conf" 2>/dev/null

  _section "Processus actifs"
  docker exec keyhome-prod-app ps aux 2>/dev/null | grep php-fpm | grep -v grep | \
    awk '{printf "  PID:%-8s CPU:%-6s MEM:%-6s %s\n",$2,$3,$4,$11}'

  _section "Statistiques (via /fpm-status)"
  docker exec keyhome-prod-web wget -qO- "http://keyhome-prod-app:9000/fpm-status" 2>/dev/null || \
    _info "(page de statut FPM non disponible — ajouter pm.status_path = /fpm-status)"
}

# ─────────────────────────────────────────────────────────────────────────────
#  COMMANDE : clean
# ─────────────────────────────────────────────────────────────────────────────
cmd_clean() {
  local sub="${1:-}"; local dry=false
  [[ "${2:-}" == "--dry-run" || "${1:-}" == "--dry-run" ]] && dry=true
  [[ "$sub" == "--dry-run" ]] && sub=""

  _header "NETTOYAGE DOCKER ${dry:+(DRY RUN)}"

  # Images dangling
  local dangling; dangling=$(docker images -f "dangling=true" -q 2>/dev/null | wc -l | tr -d ' ')
  _info "$dangling images dangling trouvées"
  if [[ "$dangling" -gt 0 && "$dry" == "false" ]]; then
    docker rmi $(docker images -f "dangling=true" -q) 2>/dev/null || true
    _ok "$dangling images dangling supprimées"
  fi

  # Containers arrêtés
  local stopped; stopped=$(docker ps -a -f "status=exited" -q 2>/dev/null | wc -l | tr -d ' ')
  _info "$stopped containers arrêtés trouvés"
  if [[ "$stopped" -gt 0 && "$dry" == "false" ]]; then
    docker container prune -f --filter "until=1h" 2>/dev/null || true
    _ok "Containers arrêtés supprimés"
  fi

  # Networks inutilisés
  [[ "$dry" == "false" ]] && { docker network prune -f --filter "until=1h" 2>/dev/null || true; _ok "Networks inutilisés supprimés"; }

  # Build cache
  if [[ "$dry" == "false" ]]; then
    docker builder prune -f --keep-storage=10GB 2>/dev/null || true
    _ok "Build cache nettoyé (10 GB conservés)"
  fi

  # Volumes orphelins (sous-commande spécifique)
  if [[ "$sub" == "volumes" ]]; then
    local orphans="keyhome-preprod_keyhome-app-code keyhome-preprod_keyhome-db-data keyhome-preprod_keyhome-meilisearch-data keyhome-preprod_keyhome-storage-data"
    for vol in $orphans; do
      if docker volume inspect "$vol" >/dev/null 2>&1; then
        _info "Volume orphelin : $vol"
        [[ "$dry" == "false" ]] && { docker volume rm "$vol" 2>/dev/null && _ok "Supprimé : $vol" || _warn "$vol en cours d'utilisation"; }
      fi
    done
  fi

  # Nettoyage complet (dangereux)
  if [[ "$sub" == "all" && "$dry" == "false" ]]; then
    _confirm "NETTOYAGE TOTAL (images, containers, volumes non utilisés). Les données des volumes nommés sont préservées."
    docker system prune -af 2>/dev/null || true
    _ok "Nettoyage complet effectué"
  fi

  echo ""
  _section "Bilan après nettoyage"
  docker system df 2>/dev/null || true
}

cmd_prune() {
  _confirm "docker system prune -f (images+containers inutilisés) ?"
  docker system prune -f
  _ok "Prune terminé"
}

# ─────────────────────────────────────────────────────────────────────────────
#  POINT D'ENTRÉE
# ─────────────────────────────────────────────────────────────────────────────
main() {
  local cmd="${1:-help}"; shift || true

  case "$cmd" in
    status)   cmd_status  "$@" ;;
    ps)       cmd_ps      "$@" ;;
    top)      cmd_top     "$@" ;;
    restart)  cmd_restart "$@" ;;
    stop)     cmd_stop    "$@" ;;
    start)    cmd_start   "$@" ;;
    exec)     cmd_exec    "$@" ;;
    shell)    cmd_shell   "$@" ;;
    logs)     cmd_logs    "$@" ;;
    errors)   cmd_errors  "$@" ;;
    db)       cmd_db      "$@" ;;
    images)   cmd_images  "$@" ;;
    pull)     cmd_pull    "$@" ;;
    rmi)      cmd_rmi     "$@" ;;
    inspect)  cmd_inspect "$@" ;;
    deploy)   cmd_deploy  "$@" ;;
    rollback) _warn "Non implémenté — utiliser: kh pull prod && kh deploy prod" ;;
    migrate)  cmd_migrate "$@" ;;
    artisan)  cmd_artisan "$@" ;;
    health)   cmd_health  "$@" ;;
    monitor)  cmd_monitor "$@" ;;
    disk)     cmd_disk    "$@" ;;
    network)  cmd_network "$@" ;;
    fpm)      cmd_fpm     "$@" ;;
    clean)    cmd_clean   "$@" ;;
    prune)    cmd_prune   "$@" ;;
    --version|-v) echo "kh $VERSION" ;;
    --help|-h|help) _usage ;;
    *) _err "Commande inconnue : $cmd"; echo ""; _usage; exit 1 ;;
  esac
}

main "$@"
