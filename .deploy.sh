#!/usr/bin/env bash
#
# Production deploy script — api.verify.aralhub.uz (Fastpanel)
#
# Run on the prod server as the site user (fastuser):
#   cd /var/www/fastuser/data/www/api.verify.aralhub.uz
#   ./.deploy.sh
#
# Override binaries if needed (Fastpanel often ships versioned PHP):
#   PHP_BIN=/usr/bin/php8.4 COMPOSER_BIN=/usr/local/bin/composer ./.deploy.sh
#
# NOTE: this performs `git reset --hard origin/<branch>` — any uncommitted
# changes on the server are discarded. The .env file is gitignored and untouched.

set -euo pipefail

APP_DIR="/var/www/fastuser/data/www/api.verify.aralhub.uz"
BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

cd "$APP_DIR"

log() { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }

# Always try to bring the app back online, even if a step fails midway.
finish() {
    "$PHP_BIN" artisan up || true
}
trap finish EXIT

log "Maintenance mode on"
"$PHP_BIN" artisan down --retry=15 || true

log "Fetching latest code ($BRANCH)"
git fetch --all --prune
git reset --hard "origin/$BRANCH"

log "Installing PHP dependencies"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Front-end assets: only the welcome page uses Vite, but build to keep the
# manifest valid. Skip gracefully if Node is not installed on the server.
if command -v npm >/dev/null 2>&1; then
    log "Building front-end assets"
    npm ci
    npm run build
else
    log "npm not found — skipping asset build"
fi

log "Running database migrations"
"$PHP_BIN" artisan migrate --force

log "Linking storage"
"$PHP_BIN" artisan storage:link || true

log "Rebuilding caches"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan optimize

log "Restarting queue workers"
"$PHP_BIN" artisan queue:restart

log "Maintenance mode off"
"$PHP_BIN" artisan up
trap - EXIT

log "Deploy finished successfully 🎉"
