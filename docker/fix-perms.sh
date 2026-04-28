#!/usr/bin/env bash
set -euo pipefail
cd /mnt/c/Users/Bookforce/Projects/RCM

echo "--- antes ---"
docker compose exec -T php sh -c 'ls -la /srv/app/var 2>&1' || true

echo "--- chown var/ a www-data en php container ---"
docker compose exec -u 0 -T php sh -c 'mkdir -p /srv/app/var/cache /srv/app/var/log /srv/app/var/uploads /srv/app/var/uploads/telesalud /srv/app/var/uploads/inscritos /srv/app/var/uploads/exports && chown -R www-data:www-data /srv/app/var && chmod -R u+rwX,g+rwX /srv/app/var'

echo "--- despues ---"
docker compose exec -T php sh -c 'ls -la /srv/app/var 2>&1'
