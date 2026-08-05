#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# De-Light API — Production Deploy Script
# Runs automatically on Railway / Render after each git push
# ─────────────────────────────────────────────────────────────────────────────

set -e

echo "🚀 De-Light API — Starting deployment..."

# Install PHP dependencies (no dev packages in production)
composer install --no-dev --optimize-autoloader --no-interaction

# Generate app key if not set
php artisan key:generate --force

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run database migrations
php artisan migrate --force

# Seed database (first deploy only — idempotent seeders)
php artisan db:seed --force

# Cache config, routes, views for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize class autoloader
php artisan optimize

echo "✅ Deployment complete!"
