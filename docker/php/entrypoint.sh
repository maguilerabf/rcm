#!/bin/sh
# Asegura que /srv/app/var sea escribible por www-data.
# Necesario porque el named volume php_var nace owned por root.
set -e

if [ -d /srv/app/var ]; then
    mkdir -p /srv/app/var/cache /srv/app/var/log /srv/app/var/uploads
    chown -R www-data:www-data /srv/app/var 2>/dev/null || true
fi

exec "$@"
