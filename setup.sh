#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
#  SteelRoot CMS — Setup Script
#  Run once after cloning: bash setup.sh
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Colors ────────────────────────────────────────────────────────────────────
R='\033[0;31m'  # red
G='\033[0;32m'  # green
Y='\033[1;33m'  # yellow
C='\033[0;36m'  # cyan
B='\033[1m'     # bold
N='\033[0m'     # reset

ok()   { echo -e "  ${G}✓${N}  $*"; }
fail() { echo -e "  ${R}✗${N}  $*"; }
warn() { echo -e "  ${Y}!${N}  $*"; }
info() { echo -e "  ${C}→${N}  $*"; }
sep()  { echo -e "${C}────────────────────────────────────────────────────${N}"; }

# ── Banner ────────────────────────────────────────────────────────────────────
echo ""
echo -e "${C}${B}"
cat <<'LOGO'
  ███████╗████████╗███████╗███████╗██╗     ██████╗  ██████╗  ██████╗ ████████╗
  ██╔════╝╚══██╔══╝██╔════╝██╔════╝██║     ██╔══██╗██╔═══██╗██╔═══██╗╚══██╔══╝
  ███████╗   ██║   █████╗  █████╗  ██║     ██████╔╝██║   ██║██║   ██║   ██║
  ╚════██║   ██║   ██╔══╝  ██╔══╝  ██║     ██╔══██╗██║   ██║██║   ██║   ██║
  ███████║   ██║   ███████╗███████╗███████╗██║  ██║╚██████╔╝╚██████╔╝   ██║
  ╚══════╝   ╚═╝   ╚══════╝╚══════╝╚══════╝╚═╝  ╚═╝ ╚═════╝  ╚═════╝   ╚═╝
LOGO
echo -e "${N}"
echo -e "${B}  SteelRoot CMS — Environment Setup${N}"
echo -e "  ${C}https://github.com/SteelRoot${N}"
echo ""
sep

ERRORS=0

# ── PHP version ───────────────────────────────────────────────────────────────
echo ""
echo -e "${B}  Checking PHP${N}"
echo ""

if command -v php &>/dev/null; then
    PHP_VER=$(php -r "echo PHP_VERSION;")
    if php -r "exit(version_compare(PHP_VERSION,'8.1.0','>=') ? 0 : 1);"; then
        ok "PHP $PHP_VER"
    else
        fail "PHP $PHP_VER — required 8.1+"
        ERRORS=$((ERRORS+1))
    fi
else
    fail "PHP not found in PATH"
    ERRORS=$((ERRORS+1))
fi

# ── PHP extensions ────────────────────────────────────────────────────────────
REQUIRED_EXTS=(pdo pdo_mysql mbstring json openssl fileinfo gd)
for ext in "${REQUIRED_EXTS[@]}"; do
    if php -r "exit(extension_loaded('$ext') ? 0 : 1);" 2>/dev/null; then
        ok "ext/$ext"
    else
        fail "ext/$ext — missing"
        ERRORS=$((ERRORS+1))
    fi
done

# ── MySQL client (optional) ───────────────────────────────────────────────────
echo ""
sep
echo ""
echo -e "${B}  Checking optional tools${N}"
echo ""
if command -v mysql &>/dev/null; then
    ok "mysql client: $(mysql --version | head -1)"
else
    warn "mysql client not found (not required, installer runs via browser)"
fi
if command -v composer &>/dev/null; then
    ok "composer: $(composer --version --no-ansi 2>/dev/null | head -1)"
else
    warn "composer not found — vendor/ must be present or committed"
fi

# ── Storage directories ───────────────────────────────────────────────────────
echo ""
sep
echo ""
echo -e "${B}  Creating storage directories${N}"
echo ""

DIRS=(
    "storage"
    "storage/cache"
    "storage/logs"
    "storage/tmp"
    "storage/tmp/sessions"
    "storage/tmp/user_tokens"
    "storage/uploads"
    "storage/uploads/gallery"
    "storage/uploads/gallery/categories"
    "storage/uploads/articles"
    "storage/uploads/articles/categories"
    "storage/uploads/users"
    "storage/uploads/menu"
    "storage/uploads/shop"
    "storage/uploads/shop/brands"
)

for d in "${DIRS[@]}"; do
    FULL="$DIR/$d"
    if [ ! -d "$FULL" ]; then
        mkdir -p "$FULL"
        ok "created  $d"
    else
        ok "exists   $d"
    fi
    chmod 775 "$FULL"

    # Place .gitkeep so empty dirs are tracked
    if [ ! -f "$FULL/.gitkeep" ]; then
        touch "$FULL/.gitkeep"
    fi
done

# ── File permissions ──────────────────────────────────────────────────────────
echo ""
sep
echo ""
echo -e "${B}  Checking write permissions${N}"
echo ""

WRITE_PATHS=(
    "storage/cache"
    "storage/logs"
    "storage/tmp"
    "storage/uploads"
)
for p in "${WRITE_PATHS[@]}"; do
    if [ -w "$DIR/$p" ]; then
        ok "writable: $p"
    else
        fail "not writable: $p — run: chmod 775 $p"
        ERRORS=$((ERRORS+1))
    fi
done

# ── Vendor ────────────────────────────────────────────────────────────────────
echo ""
sep
echo ""
echo -e "${B}  Dependencies${N}"
echo ""

if [ -d "$DIR/vendor" ]; then
    ok "vendor/ present"
else
    if command -v composer &>/dev/null && [ -f "$DIR/composer.json" ]; then
        warn "vendor/ missing — running composer install..."
        (cd "$DIR" && composer install --no-interaction --prefer-dist --no-dev)
        ok "composer install done"
    else
        warn "vendor/ missing and composer not available"
        warn "If vendor/ is committed this is fine; otherwise install composer and re-run"
    fi
fi

# ── Config files ──────────────────────────────────────────────────────────────
echo ""
sep
echo ""
echo -e "${B}  Config files${N}"
echo ""

if [ ! -d "$DIR/app/config" ]; then
    mkdir -p "$DIR/app/config"
fi

if [ -f "$DIR/app/config/app.php" ]; then
    ok "app/config/app.php — exists"
else
    warn "app/config/app.php — missing (will be created by installer)"
    if [ -f "$DIR/app/config/app.example.php" ]; then
        info "Example available: app/config/app.example.php"
    fi
fi

if [ -f "$DIR/app/config/database.php" ]; then
    ok "app/config/database.php — exists"
else
    warn "app/config/database.php — missing (will be created by installer)"
    if [ -f "$DIR/app/config/database.example.php" ]; then
        info "Example available: app/config/database.example.php"
    fi
fi

# ── Summary ───────────────────────────────────────────────────────────────────
echo ""
sep
echo ""

if [ "$ERRORS" -gt 0 ]; then
    echo -e "${R}${B}  ✗ $ERRORS issue(s) found — fix them before installing.${N}"
    echo ""
    exit 1
fi

echo -e "${G}${B}  ✓ Environment ready!${N}"
echo ""
echo -e "${B}  Next steps:${N}"
echo ""
echo -e "  ${C}1.${N} Point your web server root to:  ${B}$DIR/${N}"
echo -e "     Enable URL rewriting to prefilter.php"
echo ""
echo -e "  ${C}2.${N} Open in your browser:"
echo -e "     ${Y}http://<your-domain>/installer.php${N}"
echo ""
echo -e "  ${C}3.${N} Fill in DB credentials, site name and admin account."
echo -e "     Select modules to enable. Click ${B}Install SteelRoot${N}."
echo ""
echo -e "  ${C}4.${N} After install: ${R}delete installer.php${N}"
echo ""
echo -e "  ${C}Apache vhost example:${N}"
echo ""
echo -e "  ${C}  <VirtualHost *:80>${N}"
echo -e "  ${C}    ServerName example.com${N}"
echo -e "  ${C}    DocumentRoot $DIR${N}"
echo -e "  ${C}    <Directory $DIR>${N}"
echo -e "  ${C}      AllowOverride All${N}"
echo -e "  ${C}      Require all granted${N}"
echo -e "  ${C}    </Directory>${N}"
echo -e "  ${C}  </VirtualHost>${N}"
echo ""
echo -e "  ${C}Nginx location example:${N}"
echo ""
echo -e "  ${C}  root $DIR;${N}"
echo -e "  ${C}  index index.php;${N}"
echo -e "  ${C}  location / { try_files \$uri \$uri/ /prefilter.php\$is_args\$args; }${N}"
echo -e "  ${C}  location ~ \\.php$ { fastcgi_pass unix:/run/php/php8.1-fpm.sock; include fastcgi_params; }${N}"
echo ""
sep
echo ""
