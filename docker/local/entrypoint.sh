#!/bin/sh
set -eu
[ "${APP_ENV:-}" = local ] && [ "${DB_HOST:-}" = mysql ] && [ "${DB_DATABASE:-}" = samy_local ] || {
    echo 'Refusing non-local database/environment.' >&2
    exit 1
}
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
php artisan package:discover --ansi >/dev/null
exec "$@"
