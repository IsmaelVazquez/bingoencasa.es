#!/bin/bash
# ============================================
# BingoEnCasa - Script de Deploy para Plesk
# ============================================
#
# USO: Ejecutar desde la raíz del proyecto, tras hacer `git pull`:
#   chmod +x deploy.sh
#   ./deploy.sh
#
# REQUISITOS:
#   - PHP 8.5 disponible en Plesk (handler del dominio en 8.5)
#   - Node.js 24+ configurado en Plesk
#   - Base de datos PostgreSQL 18 accesible (DATABASE_URL en .env)
#
# VARIABLES OPCIONALES:
#   SKIP_POOL=1   -> no ejecuta el sembrado del pool de salas/cartones
#   ROOMS=300     -> nº de salas objetivo del pool (por defecto 300)
#   CARDS=2000    -> nº de cartones objetivo del pool (por defecto 2000)
# ============================================

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_DIR"

echo "============================================"
echo "BingoEnCasa - Deploy a Producción"
[ -f VERSION ] && echo "Versión: $(cat VERSION)"
echo "Directorio: $APP_DIR"
echo "============================================"
echo ""

# --- PHP 8.5 (Plesk) -----------------------------------------------------
if [ -x "/opt/plesk/php/8.5/bin/php" ]; then
  PHP="/opt/plesk/php/8.5/bin/php"
elif command -v php >/dev/null 2>&1; then
  PHP="php"
else
  echo "✗ No se encontró PHP. Configura PHP 8.5 en el dominio."; exit 1
fi

if ! "$PHP" -r 'exit(PHP_VERSION_ID < 80500 ? 1 : 0);'; then
  echo "✗ Se requiere PHP >= 8.5 (actual: $("$PHP" -r 'echo PHP_VERSION;'))."
  echo "  Cambia el handler PHP del dominio a 8.5 en Plesk y reintenta."
  exit 1
fi
echo "✓ PHP $("$PHP" -r 'echo PHP_VERSION;') ($PHP)"

# --- Node.js (Plesk) -----------------------------------------------------
for v in 25 24 20; do
  if [ -d "/opt/plesk/node/$v/bin" ]; then
    export PATH="/opt/plesk/node/$v/bin:$PATH"
    echo "✓ Node.js $v"
    break
  fi
done
command -v npm >/dev/null 2>&1 || { echo "✗ npm no encontrado. Configura Node.js en Plesk."; exit 1; }

# --- Composer ------------------------------------------------------------
if command -v composer >/dev/null 2>&1; then
  COMPOSER="composer"
elif [ -f "$APP_DIR/composer.phar" ]; then
  COMPOSER="$PHP $APP_DIR/composer.phar"
else
  echo "→ Composer no encontrado, descargando..."
  "$PHP" -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  "$PHP" composer-setup.php --quiet
  rm -f composer-setup.php
  COMPOSER="$PHP $APP_DIR/composer.phar"
fi

echo ""
echo "[1/6] Instalando dependencias PHP..."
$COMPOSER install --no-dev --optimize-autoloader --no-interaction
echo "✓ Dependencias PHP listas"

echo ""
echo "[2/6] Instalando dependencias de frontend..."
# Cache por hash del lockfile: solo reinstala si cambió package-lock.json
LOCK_HASH="$(md5sum package-lock.json 2>/dev/null | cut -d' ' -f1)"
CACHED_HASH=""
[ -f node_modules/.lockfile-hash ] && CACHED_HASH="$(cat node_modules/.lockfile-hash)"
if [ -n "$LOCK_HASH" ] && [ "$LOCK_HASH" = "$CACHED_HASH" ] && [ -d node_modules ]; then
  echo "✓ Dependencias sin cambios (cache)"
else
  npm ci --no-audit --no-fund
  echo "$LOCK_HASH" > node_modules/.lockfile-hash
  echo "✓ Dependencias instaladas"
fi

echo ""
echo "[3/6] Compilando assets (Vite, terser, sin sourcemaps)..."
BUILD_START=$(date +%s)
npm run build
echo "✓ Assets compilados en $(( $(date +%s) - BUILD_START ))s"

echo ""
echo "[4/6] Aplicando migraciones de base de datos..."
"$PHP" bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
echo "✓ Migraciones al día"

echo ""
echo "[5/6] Sembrando pool de salas y cartones..."
if [ "${SKIP_POOL:-0}" = "1" ]; then
  echo "↷ Omitido (SKIP_POOL=1)"
else
  "$PHP" bin/console app:init-pool --rooms="${ROOMS:-300}" --cards="${CARDS:-2000}" --no-interaction
fi

echo ""
echo "[6/6] Limpiando y calentando caché de producción..."
"$PHP" bin/console cache:clear --env=prod --no-debug
"$PHP" bin/console cache:warmup --env=prod --no-debug
# Permisos de assets y caché
chmod -R 755 public/build 2>/dev/null || true
chmod -R 775 var 2>/dev/null || true
echo "✓ Caché lista"

echo ""
echo "============================================"
echo "DEPLOY COMPLETADO ($(cat VERSION 2>/dev/null))"
echo "============================================"
echo ""
echo "Logs de error:  tail -f var/log/prod.log"
