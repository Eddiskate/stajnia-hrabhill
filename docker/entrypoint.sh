#!/bin/bash
set -euo pipefail

cd /var/www/html

if [ -n "${PORT:-}" ] && [ "$PORT" != "80" ]; then
  sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf || true
  sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf || true
fi

mkdir -p var/cache var/log
chown -R www-data:www-data var

if [ -z "${APP_SECRET:-}" ]; then
  echo "WARNING: APP_SECRET is empty — set it in EasyPanel env"
fi

su -s /bin/bash www-data -c "php bin/console cache:warmup --env=prod" 2>/dev/null || \
  php bin/console cache:warmup --env=prod 2>/dev/null || true

exec "$@"
