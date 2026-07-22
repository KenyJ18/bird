# ==============================================
# Bird API - Laravel 13 Multi-Stage Build
# ==============================================

# ==============================================
# Stage 1: Base
# ==============================================
FROM php:8.3-cli-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    postgresql-dev \
    linux-headers \
    ${PHPIZE_DEPS}

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    xml \
    bcmath \
    gd \
    pcntl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ==============================================
# Stage 2: Development
# ==============================================
FROM base AS development

# Install Swoole for Laravel Octane
RUN pecl install swoole && docker-php-ext-enable swoole

# Copy application files
COPY . .

# Install dependencies (including dev)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port
EXPOSE 8080

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8080"]

# ==============================================
# Stage 3: Production
# ==============================================
FROM base AS production

# Copy application files
COPY . .

# Install dependencies (production only)
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Optimize Laravel
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

EXPOSE 8080

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8080"]
