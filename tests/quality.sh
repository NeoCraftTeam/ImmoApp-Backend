#!/usr/bin/env zsh
# ============================================================
#  🔍 Code Quality Pipeline — PHPStan · Rector · Pint · Tests · Insights
# ============================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'
BOLD='\033[1m'

# ─── Help ──────────────────────────────────────────────────
show_help() {
    echo ""
    echo -e "${BOLD}${CYAN}🔍 Code Quality Pipeline${NC}"
    echo ""
    echo -e "${BOLD}Usage:${NC}  ./tests/quality.sh [options]"
    echo ""
    echo -e "${BOLD}Options:${NC}"
    echo "  --fix        Applique les corrections Rector + Pint (défaut: dry-run)"
    echo "  --no-test    Ignore les tests (php artisan test)"
    echo "  --only-fix   Applique Rector + Pint uniquement, sans PHPStan/Tests/Insights"
    echo "  -h, --help   Affiche cette aide"
    echo ""
    echo -e "${BOLD}Ordre d'exécution:${NC}"
    echo "  1. PHPStan    — Analyse statique"
    echo "  2. Rector     — Refactoring automatique"
    echo "  3. Pint       — Code style"
    echo "  4. Tests      — php artisan test"
    echo "  5. Insights   — Qualité globale"
    echo ""
    echo -e "${BOLD}Exemples:${NC}"
    echo "  ./tests/quality.sh              # Vérification complète (dry-run)"
    echo "  ./tests/quality.sh --fix        # Corrige + vérifie tout"
    echo "  ./tests/quality.sh --fix --no-test  # Corrige, skip les tests"
    echo "  ./tests/quality.sh --only-fix   # Juste Rector + Pint (rapide)"
    echo ""
    exit 0
}

# ─── Parse args ────────────────────────────────────────────
FIX=false
RUN_TESTS=true
ONLY_FIX=false

for arg in "$@"; do
    case $arg in
        --fix)      FIX=true ;;
        --no-test)  RUN_TESTS=false ;;
        --only-fix) FIX=true; ONLY_FIX=true ;;
        -h|--help)  show_help ;;
        *)          echo "Option inconnue: $arg"; show_help ;;
    esac
done

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_DIR"

echo ""
echo -e "${BOLD}${CYAN}═══════════════════════════════════════${NC}"
echo -e "${BOLD}${CYAN}  🔍  Code Quality Pipeline${NC}"
echo -e "${BOLD}${CYAN}═══════════════════════════════════════${NC}"
echo ""

PASS=0
FAIL=0

# ─── 1. PHPStan ────────────────────────────────────────────
echo -e "${YELLOW}▸ [1/5] PHPStan${NC}"
./vendor/bin/phpstan analyse 2>&1
if [[ $? -eq 0 ]]; then
    echo -e "${GREEN}  ✅ PHPStan — aucune erreur${NC}"; PASS=$((PASS + 1))
else
    echo -e "${RED}  ❌ PHPStan — erreurs trouvées${NC}"; FAIL=$((FAIL + 1))
fi
echo ""

# ─── 2. Rector ─────────────────────────────────────────────
echo -e "${YELLOW}▸ [2/5] Rector${NC}"
if $FIX; then
    ./vendor/bin/rector process 2>&1
    if [[ $? -eq 0 ]]; then
        echo -e "${GREEN}  ✅ Rector — appliqué${NC}"; PASS=$((PASS + 1))
    else
        echo -e "${RED}  ❌ Rector — échec${NC}"; FAIL=$((FAIL + 1))
    fi
else
    ./vendor/bin/rector process --dry-run 2>&1
    if [[ $? -eq 0 ]]; then
        echo -e "${GREEN}  ✅ Rector — clean${NC}"; PASS=$((PASS + 1))
    else
        echo -e "${RED}  ❌ Rector — changements nécessaires (lance avec --fix)${NC}"; FAIL=$((FAIL + 1))
    fi
fi
echo ""

# ─── 3. Pint ──────────────────────────────────────────────
echo -e "${YELLOW}▸ [3/5] Laravel Pint${NC}"
if $FIX; then
    ./vendor/bin/pint 2>&1
    if [[ $? -eq 0 ]]; then
        echo -e "${GREEN}  ✅ Pint — corrigé${NC}"; PASS=$((PASS + 1))
    else
        echo -e "${RED}  ❌ Pint — échec${NC}"; FAIL=$((FAIL + 1))
    fi
else
    ./vendor/bin/pint --test 2>&1
    if [[ $? -eq 0 ]]; then
        echo -e "${GREEN}  ✅ Pint — clean${NC}"; PASS=$((PASS + 1))
    else
        echo -e "${RED}  ❌ Pint — problèmes trouvés (lance avec --fix)${NC}"; FAIL=$((FAIL + 1))
    fi
fi
echo ""

# Si --only-fix, on s'arrête ici
if $ONLY_FIX; then
    echo -e "${BOLD}${GREEN}  ✅ Rector + Pint terminés (--only-fix)${NC}"
    echo ""
    exit 0
fi

# ─── 4. Tests ──────────────────────────────────────────────
if $RUN_TESTS; then
    echo -e "${YELLOW}▸ [4/5] Tests${NC}"
    php artisan test 2>&1
    if [[ $? -eq 0 ]]; then
        echo -e "${GREEN}  ✅ Tests — tous passés${NC}"; PASS=$((PASS + 1))
    else
        echo -e "${RED}  ❌ Tests — échecs${NC}"; FAIL=$((FAIL + 1))
    fi
else
    echo -e "${YELLOW}▸ [4/5] Tests — ignorés (--no-test)${NC}"
fi
echo ""

# ─── 5. PHP Insights ──────────────────────────────────────
echo -e "${YELLOW}▸ [5/5] PHP Insights${NC}"
./vendor/bin/phpinsights --no-interaction --summary 2>&1
if [[ $? -eq 0 ]]; then
    echo -e "${GREEN}  ✅ PHP Insights — complet${NC}"; PASS=$((PASS + 1))
else
    echo -e "${RED}  ❌ PHP Insights — échec${NC}"; FAIL=$((FAIL + 1))
fi

# ─── Résumé ───────────────────────────────────────────────
echo ""
echo -e "${BOLD}${CYAN}═══════════════════════════════════════${NC}"
if [[ $FAIL -eq 0 ]]; then
    echo -e "${BOLD}${GREEN}  ✅ $PASS vérification(s) réussie(s) !${NC}"
else
    echo -e "${BOLD}${RED}  ⚠️  $FAIL échec(s), $PASS réussite(s)${NC}"
fi
echo -e "${BOLD}${CYAN}═══════════════════════════════════════${NC}"
echo ""

exit $FAIL
