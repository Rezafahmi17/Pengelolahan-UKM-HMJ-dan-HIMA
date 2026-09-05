#!/usr/bin/env sh
cd "$(dirname "$0")"
[ -f .env ] || cp .env.example .env
echo "SIM ORMAWA & HMJ: http://127.0.0.1:8000"
php -S 127.0.0.1:8000 -t public
