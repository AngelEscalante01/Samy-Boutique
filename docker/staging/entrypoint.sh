#!/bin/sh
set -eu
[ "${SAMY_QA_ONLY:-}" = 1 ] && [ "${APP_ENV:-}" = staging ] && [ "${DB_HOST:-}" = mysql ] && [ "${DB_DATABASE:-}" = samy_vps_qa ] && [ "${R2_PUBLIC_BUCKET:-}" = samy-public-test ] && [ "${R2_PRIVATE_BUCKET:-}" = samy-private-test ] && [ "${FIREBASE_PROJECT_ID:-}" = samy-boutique-dev ] || { echo 'Refusing non-QA environment' >&2; exit 1; }
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
php artisan package:discover --ansi >/dev/null
exec "$@"
