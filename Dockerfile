# ─────────────────────────────────────────────────────────────────────────────
# De-Light API — Production Dockerfile
# PHP 8.2 + Laravel 12 + Nginx (single container, Railway/Render compatible)
# ─────────────────────────────────────────────────────────────────────────────

FROM php:8.2-fpm-alpine AS base

# System dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    zip \
    unzip \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    mysql-client \
    bash

# PHP extensions
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        xml \
        intl \
        opcache

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ─── Build stage ──────────────────────────────────────────────────────────────
FROM base AS builder

WORKDIR /var/www/html

# Copy composer files first (layer caching)
COPY composer.json composer.lock ./

# Install PHP deps without dev packages
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

# Copy application source
COPY . .

# Finish autoloader
RUN composer dump-autoload --optimize --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# ─── Production stage ─────────────────────────────────────────────────────────
FROM base AS production

WORKDIR /var/www/html

# Copy built app from builder
COPY --from=builder --chown=www-data:www-data /var/www/html .

# ── Nginx config ──────────────────────────────────────────────────────────────
RUN mkdir -p /etc/nginx/conf.d
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/conf.d/default.conf

# ── PHP-FPM config ────────────────────────────────────────────────────────────
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# ── PHP opcache config ────────────────────────────────────────────────────────
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# ── Supervisor config (runs nginx + php-fpm + queue worker) ──────────────────
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ── Startup script ────────────────────────────────────────────────────────────
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
