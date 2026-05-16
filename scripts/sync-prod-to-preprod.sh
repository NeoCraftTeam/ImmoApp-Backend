#!/usr/bin/env bash
# =============================================================================
# sync-prod-to-preprod.sh — Copie la base prod vers preprod (ou flush preprod)
# =============================================================================
# Usage:
#   ./scripts/sync-prod-to-preprod.sh clone   # Dump prod → restore preprod
#   ./scripts/sync-prod-to-preprod.sh flush   # DROP + recreate preprod vide
#   ./scripts/sync-prod-to-preprod.sh status  # Affiche le nombre de lignes par table
#
# Prérequis : docker, containers keyhome-prod-db et keyhome-preprod-backend up.
# =============================================================================
set -euo pipefail

PROD_CONTAINER="keyhome-prod-db"
PREPROD_APP="keyhome-preprod-backend"

# Lire les credentials depuis les .env
PROD_DB=$(grep -E "^DB_DATABASE=" /opt/keyhome/.env 2>/dev/null | cut -d= -f2 | tr -d "[:space:]'\"")
PROD_USER=$(grep -E "^DB_USERNAME=" /opt/keyhome/.env 2>/dev/null | cut -d= -f2 | tr -d "[:space:]'\"")
PROD_PASS=$(grep -E "^DB_PASSWORD=" /opt/keyhome/.env 2>/dev/null | cut -d= -f2 | tr -d "[:space:]'\"")

PREPROD_DB=$(grep -E "^DB_DATABASE=" /opt/keyhome-preprod/.env 2>/dev/null | cut -d= -f2 | tr -d "[:space:]'\"")
PREPROD_USER=$(grep -E "^DB_USERNAME=" /opt/keyhome-preprod/.env 2>/dev/null | cut -d= -f2 | tr -d "[:space:]'\"")

PROD_DB=${PROD_DB:-keyhome_prod}
PREPROD_DB=${PREPROD_DB:-keyhome_preprod}
PROD_USER=${PROD_USER:-cedrick}
PREPROD_USER=${PREPROD_USER:-cedrick}

CMD=${1:-status}

_psql_prod() {
  PGPASSWORD="$PROD_PASS" docker exec -i "$PROD_CONTAINER" \
    psql -U "$PROD_USER" "$@"
}

_psql_prod_db() {
  PGPASSWORD="$PROD_PASS" docker exec -i "$PROD_CONTAINER" \
    psql -U "$PROD_USER" -d "$PROD_DB" "$@"
}

case "$CMD" in

  # ── Clone prod → preprod ────────────────────────────────────────────────────
  clone)
    echo "⚠️  Cette opération va ÉCRASER la base preprod ($PREPROD_DB) avec les données prod ($PROD_DB)."
    read -r -p "Confirmer ? (yes/N) : " CONFIRM
    [[ "$CONFIRM" == "yes" ]] || { echo "Annulé."; exit 0; }

    DUMP_FILE="/tmp/keyhome_prod_$(date +%Y%m%d_%H%M%S).dump"

    echo "📦 Dump de $PROD_DB..."
    PGPASSWORD="$PROD_PASS" docker exec "$PROD_CONTAINER" \
      pg_dump -U "$PROD_USER" -Fc --no-owner --no-acl "$PROD_DB" > "$DUMP_FILE"
    echo "   Dump : $DUMP_FILE ($(du -sh "$DUMP_FILE" | cut -f1))"

    echo "🗑  DROP + CREATE $PREPROD_DB..."
    _psql_prod -d postgres -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$PREPROD_DB' AND pid <> pg_backend_pid();" >/dev/null 2>&1 || true
    _psql_prod -d postgres -c "DROP DATABASE IF EXISTS \"$PREPROD_DB\";"
    _psql_prod -d postgres -c "CREATE DATABASE \"$PREPROD_DB\" OWNER \"$PREPROD_USER\";"

    echo "♻️  Restore vers $PREPROD_DB..."
    PGPASSWORD="$PROD_PASS" docker exec -i "$PROD_CONTAINER" \
      pg_restore -U "$PROD_USER" -d "$PREPROD_DB" --no-owner --no-acl --exit-on-error < "$DUMP_FILE" || {
        echo "⚠️  Quelques erreurs pg_restore (extensions manquantes) — généralement ignorables."
      }

    echo "🔄 Lancement des migrations preprod (pour aligner le schéma si besoin)..."
    docker exec "$PREPROD_APP" php artisan migrate --force || true

    rm -f "$DUMP_FILE"
    echo "✅ Preprod synchronisée avec prod."
    ;;

  # ── Flush preprod (vide, schéma vide) ──────────────────────────────────────
  flush)
    echo "⚠️  Cette opération va SUPPRIMER toutes les données de $PREPROD_DB."
    read -r -p "Confirmer ? (yes/N) : " CONFIRM
    [[ "$CONFIRM" == "yes" ]] || { echo "Annulé."; exit 0; }

    echo "🗑  DROP + CREATE $PREPROD_DB..."
    _psql_prod -d postgres -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$PREPROD_DB' AND pid <> pg_backend_pid();" >/dev/null 2>&1 || true
    _psql_prod -d postgres -c "DROP DATABASE IF EXISTS \"$PREPROD_DB\";"
    _psql_prod -d postgres -c "CREATE DATABASE \"$PREPROD_DB\" OWNER \"$PREPROD_USER\";"

    echo "🗺  Extensions PostGIS..."
    _psql_prod -d "$PREPROD_DB" -c "CREATE EXTENSION IF NOT EXISTS postgis; CREATE EXTENSION IF NOT EXISTS postgis_topology;" || true

    echo "🔄 Migrations preprod (schéma vide)..."
    docker exec "$PREPROD_APP" php artisan migrate --force
    docker exec "$PREPROD_APP" php artisan db:seed --class=DatabaseSeeder --force 2>/dev/null || true

    echo "✅ Preprod vidée et réinitialisée."
    ;;

  # ── Statut ──────────────────────────────────────────────────────────────────
  status)
    echo "=== Bases disponibles ==="
    _psql_prod -d postgres -c "\l" | grep -E "keyhome|Name"
    echo ""
    echo "=== Lignes par table (prod) ==="
    _psql_prod_db -c "SELECT schemaname, tablename, n_live_tup AS rows FROM pg_stat_user_tables ORDER BY n_live_tup DESC LIMIT 20;"
    echo ""
    echo "=== Lignes par table (preprod) ==="
    PGPASSWORD="$PROD_PASS" docker exec -i "$PROD_CONTAINER" \
      psql -U "$PROD_USER" -d "$PREPROD_DB" \
      -c "SELECT schemaname, tablename, n_live_tup AS rows FROM pg_stat_user_tables ORDER BY n_live_tup DESC LIMIT 20;" 2>/dev/null || echo "(preprod vide ou inaccessible)"
    ;;

  *)
    echo "Usage: $0 {clone|flush|status}"
    exit 1
    ;;
esac
