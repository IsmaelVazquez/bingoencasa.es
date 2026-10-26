#!/usr/bin/env sh
set -e

if [ -f "/usr/local/etc/php/conf.d/xdebug.ini.template" ]; then
  envsubst < /usr/local/etc/php/conf.d/xdebug.ini.template > /usr/local/etc/php/conf.d/xdebug.ini
fi

exec docker-php-entrypoint "$@"
