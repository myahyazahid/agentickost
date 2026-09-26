#!/usr/bin/env bash
#
# Deploy the given commit into the current checkout. Called by CI over SSH:
#   bash deploy/deploy.sh <commit-sha>
#
# The body lives in main() so bash parses the whole script before `git reset`
# replaces this file on disk.

set -euo pipefail

main() {
    local ref="${1:-origin/main}"

    cd "$(dirname "$0")/.."

    echo "==> Fetching ${ref}"
    git fetch --prune origin
    git reset --hard "${ref}"

    echo "==> Installing dependencies"
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    npm ci --no-audit --no-fund
    npm run build

    echo "==> Migrating database"
    php artisan migrate --force

    echo "==> Syncing built-in roles and permissions"
    php artisan access:sync-roles

    echo "==> Caching config, routes, views, and Filament components"
    php artisan optimize
    php artisan filament:optimize

    echo "==> Restarting queue workers"
    php artisan horizon:terminate

    echo "==> Deployed $(git rev-parse --short HEAD)"
}

main "$@"
