# ====================================
# Laravel API Dockerfile - Multi-stage
# ====================================

# ------------------------------
# Base Stage
# ------------------------------
FROM php:8.3-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    oniguruma-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    nginx \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    mbstring \
    zip \
    pcntl \
    bcmath

# Install Swoole for Laravel Octane
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install swoole \
    && docker-php-ext-enable swoole \
    && apk del .build-deps

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ------------------------------
# Development Stage
# ------------------------------
FROM base AS development

# Set PHP configuration for development
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Install Xdebug
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps

# Copy custom php.ini
COPY docker/php.ini $PHP_INI_DIR/conf.d/custom.ini

# Copy application files
COPY --chown=www-data:www-data . .

# Install dependencies
RUN composer install --no-interaction --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8080"]

# ------------------------------
# Production Stage
# ------------------------------
FROM base AS production

# Set PHP configuration for production
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy custom php.ini
COPY docker/php.ini $PHP_INI_DIR/conf.d/custom.ini

# Copy application files
COPY --chown=www-data:www-data . .

# Install dependencies (production only)
RUN composer install --no-dev --no-interaction --optimize-autoloader --classmap-authoritative

# Optimize Laravel
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8080"]
