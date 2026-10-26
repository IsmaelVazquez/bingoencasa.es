#!/bin/sh
set -e

cd /var/www/html

if [ -z "$DATABASE_URL" ]; then
  echo "ERROR: DATABASE_URL no está definida" >&2
  exit 1
fi

echo "Esperando a Postgres..."
DB_HOST=$(echo "$DATABASE_URL" | sed -n 's|.*@\([^:/]*\).*|\1|p')
DB_PORT=$(echo "$DATABASE_URL" | sed -n 's|.*:\([0-9]*\)/.*|\1|p')
DB_PORT=${DB_PORT:-5432}

retries=60
until pg_isready -h "$DB_HOST" -p "$DB_PORT" -q > /dev/null 2>&1 || [ "$retries" -eq 0 ]; do
  retries=$((retries - 1))
  sleep 1
done

if [ "$retries" -eq 0 ]; then
  echo "ERROR: Postgres no disponible tras 60s" >&2
  exit 1
fi
echo "Postgres disponible."

echo "Ejecutando migraciones..."
php bin/console doctrine:migrations:migrate --env=prod --no-interaction --allow-no-migration

echo "Regenerando cache de producción..."
rm -rf var/cache/prod
php bin/console cache:warmup --env=prod --no-debug

chown -R www-data:www-data var

exec "$@"
