# ==============================================================================
# Production Multi-Stage Hardened Dockerfile
# NORSU Order of Payment System
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Composer Vendor Dependencies Builder
# ------------------------------------------------------------------------------
FROM composer:2.8 AS composer_builder

WORKDIR /app

COPY composer.json composer.lock ./
COPY app ./app

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# ------------------------------------------------------------------------------
# Stage 2: Node.js Client Assets Builder
# ------------------------------------------------------------------------------
FROM node:22-alpine AS node_builder

WORKDIR /app

RUN corepack enable && corepack prepare pnpm@12.9.1 --activate

COPY package.json pnpm-lock.yaml ./

RUN pnpm install --frozen-lockfile --ignore-scripts

COPY resources ./resources
COPY public ./public
COPY vite.config.ts tsconfig.json eslint.config.js components.json ./

RUN pnpm run build

# ------------------------------------------------------------------------------
# Stage 3: Hardened Production Runtime (Alpine Linux)
# ------------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS runner

# Install runtime OS dependencies
RUN apk add --no-cache \
    curl \
    fcgi \
    icu-data-full \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    sqlite-libs \
    sqlite-dev \
    oniguruma \
    libxml2

# Install and enable essential PHP extensions
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    bcmath \
    ctype \
    curl \
    dom \
    fileinfo \
    filter \
    gd \
    hash \
    intl \
    json \
    mbstring \
    opcache \
    pcntl \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    session \
    tokenizer \
    xml \
    zip

# Production security hardening configuration for PHP
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'expose_php=Off'; \
    echo 'display_errors=Off'; \
    echo 'display_startup_errors=Off'; \
    echo 'log_errors=On'; \
    echo 'error_reporting=E_ALL & ~E_DEPRECATED & ~E_STRICT'; \
    } > /usr/local/etc/php/conf.d/security-hardening.ini

WORKDIR /var/www/html

# Copy application source code
COPY . .

# Copy vendor dependencies from composer_builder and built frontend assets from node_builder
COPY --from=composer_builder /app/vendor /var/www/html/vendor
COPY --from=node_builder /app/public/build /var/www/html/public/build

# Setup runtime storage, cache, and database directories with proper non-root ownership
RUN mkdir -p /var/www/html/storage/logs \
             /var/www/html/storage/framework/cache/data \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/views \
             /var/www/html/bootstrap/cache \
             /var/www/html/database \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Run as unprivileged user
USER www-data

EXPOSE 8000 9000

# Default entry command
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
