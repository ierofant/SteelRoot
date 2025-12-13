#!/bin/bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CSS_DIR="$ROOT/assets/css"
SCSS_DIR="$ROOT/assets/scss"
BACKUP_DIR="$CSS_DIR/backups"
STAMP="$(date +"%Y%m%d_%H%M%S")"

echo "[build] root: $ROOT"
SASS_BIN=$(command -v sass || true)
if [ -z "$SASS_BIN" ]; then
  # Try npx
  if command -v npx >/dev/null 2>&1; then
    SASS_BIN="npx sass"
  else
    echo "[build] ❌ sass не найден. Установите dart-sass (npm i -g sass) или npx sass и повторите."
    exit 1
  fi
fi

mkdir -p "$BACKUP_DIR"
BACKUP_FILE="$BACKUP_DIR/css_backup_$STAMP.tar.gz"
echo "[build] делаю бэкап CSS в $BACKUP_FILE"
tar --exclude="backups/*" -czf "$BACKUP_FILE" -C "$CSS_DIR" .

echo "[build] компиляция SCSS → CSS (using $SASS_BIN)"
# Фронт
$SASS_BIN "$SCSS_DIR/app.scss" "$CSS_DIR/app.css" --no-source-map
# Админка
$SASS_BIN "$SCSS_DIR/admin.scss" "$CSS_DIR/admin-theme.css" --no-source-map

echo "[build] ✅ готово. Бэкап: $BACKUP_FILE"
