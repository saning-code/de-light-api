#!/bin/bash
set -e

echo "🚀 Starting De-Light API..."

cd /var/www/html

# Generate app key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "⚙️  Generating APP_KEY..."
    php artisan key:generate --force
fi

# Generate JWT secret if not set
if [ -z "$JWT_SECRET" ] || [ "$JWT_SECRET" = "" ]; then
    echo "⚙️  Generating JWT_SECRET..."
    php artisan jwt:secret --force
fi

# Wait for database to be ready
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_CONN="${DB_CONNECTION:-pgsql}"

echo "⏳ Waiting for database ($DB_CONN @ $DB_HOST:$DB_PORT)..."
max_tries=30
count=0

if [ "$DB_CONN" = "pgsql" ]; then
    until php -r "
        try {
            \$pdo = new PDO(
                'pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'),
                getenv('DB_USERNAME'),
                getenv('DB_PASSWORD')
            );
            exit(0);
        } catch(Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; do
        count=$((count+1))
        if [ $count -ge $max_tries ]; then
            echo "❌ Database not ready after $max_tries attempts."
            exit 1
        fi
        echo "   attempt $count/$max_tries — retrying in 2s..."
        sleep 2
    done
else
    until php -r "
        \$conn = @mysqli_connect(
            getenv('DB_HOST'), getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'), getenv('DB_DATABASE'),
            (int)getenv('DB_PORT') ?: 3306
        );
        exit(\$conn ? 0 : 1);
    " 2>/dev/null; do
        count=$((count+1))
        if [ $count -ge $max_tries ]; then
            echo "❌ Database not ready after $max_tries attempts."
            exit 1
        fi
        echo "   attempt $count/$max_tries — retrying in 2s..."
        sleep 2
    done
fi

echo "✅ Database is ready."

# Run migrations
echo "⚙️  Running migrations..."
php artisan migrate --force

# Seed database (idempotent — uses firstOrCreate)
echo "⚙️  Seeding database..."
php artisan db:seed --force || echo "⚠️  Seeding skipped or already done"

# Clear all stale caches first
echo "⚙️  Clearing stale caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan optimize:clear 2>/dev/null || true

# Cache everything for performance
echo "⚙️  Caching config, routes, views..."
php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan optimize

# Fix storage permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "✅ De-Light API is ready!"

# Start supervisor (nginx + php-fpm + queue worker)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
