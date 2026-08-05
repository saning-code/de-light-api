#!/bin/bash
set -e

echo "🚀 Starting De-Light API..."

cd /var/www/html

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "⚙️  Generating APP_KEY..."
    php artisan key:generate --force
fi

# Wait for MySQL to be ready (Railway provides it via env vars)
if [ "$DB_CONNECTION" = "mysql" ]; then
    echo "⏳ Waiting for MySQL..."
    max_tries=30
    count=0
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
            echo "❌ MySQL not ready after $max_tries attempts. Exiting."
            exit 1
        fi
        echo "   attempt $count/$max_tries — retrying in 2s..."
        sleep 2
    done
    echo "✅ MySQL is ready."
fi

# Run migrations
echo "⚙️  Running migrations..."
php artisan migrate --force

# Seed on first deploy (safe — seeders use updateOrCreate)
echo "⚙️  Seeding database..."
php artisan db:seed --force || echo "Seeding skipped (already done)"

# Cache for performance
echo "⚙️  Caching config, routes, views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Fix storage permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "✅ De-Light API is ready on port 80!"

# Start supervisor (nginx + php-fpm + queue worker)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
