#!/usr/bin/env bash
# One-time helper: generate .env with random secrets.
# Re-running overwrites .env (the existing file is backed up to .env.bak).
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ -f .env ]]; then
    cp .env .env.bak
    echo "Existing .env backed up to .env.bak"
fi

APP_SECRET=$(openssl rand -hex 32)
POSTGRES_PW=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)
ADMIN_PW=$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-16)

cp .env.docker.example .env
sed -i "s|APP_SECRET=.*|APP_SECRET=${APP_SECRET}|" .env
sed -i "s|POSTGRES_PASSWORD=.*|POSTGRES_PASSWORD=${POSTGRES_PW}|" .env

echo "---"
echo "GENERATED_APP_SECRET=${APP_SECRET}"
echo "GENERATED_POSTGRES_PW=${POSTGRES_PW}"
echo "SUGGESTED_ADMIN_PW=${ADMIN_PW}"
echo "---"
echo "Wrote: $(pwd)/.env"
