#!/bin/sh
set -eu
fail() { echo "Samy release: $1" >&2; exit 78; }
[ "${DB_HOST:-}" = mysql ] || fail 'unexpected database host'
[ "${DB_DATABASE:-}" != samy_restore ] || fail 'backup database forbidden'
[ "${APP_DEBUG:-}" = false ] || fail 'debug must be disabled'
[ "${SAMY_DELETE_IMAGES_ON_SALE:-false}" = false ] || fail 'automatic image deletion remains postponed'
case "${SAMY_RELEASE_MODE:-}" in
  preview)
    [ "${APP_ENV:-}" = staging ] && [ "${DB_DATABASE:-}" = samy_release_preview ] || fail 'preview requires isolated fixtures'
    [ "${MAIL_MAILER:-}" = log ] && [ "${NOTIFICATIONS_PUSH_ENABLED:-}" = false ] || fail 'preview cannot send messages'
    ;;
  live)
    [ "${APP_ENV:-}" = production ] && [ "${DB_DATABASE:-}" = samy_production ] || fail 'unexpected production destination'
    [ "${APP_URL:-}" = https://sistema.samyboutique.com.mx ] || fail 'unexpected production URL'
    [ "${PUBLIC_URL:-}" = https://samyboutique.com.mx ] || fail 'unexpected public catalog URL'
    [ "${SYSTEM_URL:-}" = https://sistema.samyboutique.com.mx ] || fail 'unexpected system URL'
    [ "${SESSION_SECURE_COOKIE:-}" = true ] || fail 'secure cookies required'
    [ "${R2_PUBLIC_BUCKET:-}" = samy-public-prod ] && [ "${R2_PRIVATE_BUCKET:-}" = samy-private-prod ] || fail 'production buckets required'
    [ "${FIREBASE_PROJECT_ID:-}" = samy-boutique ] || fail 'production Firebase required'
    [ -s /release-state/cutover-approved ] || fail 'final cutover approval missing'
    ;;
  *) fail 'explicit release mode required' ;;
esac
exec "$@"
