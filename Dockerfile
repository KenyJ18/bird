# ==============================================
# Bird Platform - Multi-Stage Build
# Next.js 16 (Static Export) + Laravel 13 (Swoole/Octane)
# ==============================================

# ==============================================
# Stage 1: PHP Base
# PHP 8.3 + required extensions + Swoole + Redis
# Composer はインストールしない（api-builder / development で個別に追加）
# ==============================================
FROM php:8.3-cli-alpine AS php-base

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
    ${PHPIZE_DEPS} \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pdo_mysql \
    mbstring \
    xml \
    bcmath \
    gd \
    pcntl \
    && pecl install redis-6.0.2 swoole-5.1.3 \
    && docker-php-ext-enable redis swoole \
    && apk del ${PHPIZE_DEPS} linux-headers \
    && rm -rf /tmp/pear

# ==============================================
# Stage 2: Frontend Builder
# Next.js static export -> /app/out
# ==============================================
FROM node:20-alpine AS frontend-builder

RUN corepack enable && corepack prepare pnpm@8.15.4 --activate

WORKDIR /app

COPY frontend/package.json frontend/pnpm-lock.yaml ./

RUN pnpm config set network-timeout 600000 \
    && pnpm install --frozen-lockfile

COPY frontend/ .

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1

RUN pnpm build

# ==============================================
# Stage 3: API Builder
# Composer deps (production only, --no-dev)
# ==============================================
FROM php-base AS api-builder

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api

# composer.json/lock を先にコピーしてレイヤーキャッシュを活用
COPY apps/api/composer.json apps/api/composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

COPY apps/api/ .

RUN chown -R www-data:www-data storage bootstrap/cache

# ==============================================
# Stage 4: Development
# API only (use docker-compose for full stack)
# ==============================================
FROM php-base AS development

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# composer.json/lock を先にコピーしてレイヤーキャッシュを活用
# → ソースコードのみ変更した場合に composer install が再実行されない
COPY apps/api/composer.json apps/api/composer.lock ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

COPY apps/api/ .

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

CMD ["php", "artisan", "octane:start", \
     "--server=swoole", "--host=0.0.0.0", "--port=8080", "--watch"]

# ==============================================
# Stage 5: Production
# Nginx (port 80) + Swoole/Octane (port 8080 internal)
# Supervisor manages both processes
# ==============================================
FROM php-base AS production

# Install Nginx and Supervisor
RUN apk add --no-cache nginx supervisor \
    && mkdir -p /var/log/supervisor \
    && mkdir -p /run/nginx

# Copy built frontend (Next.js static export)
COPY --from=frontend-builder /app/out /usr/share/nginx/html

# Copy built API
COPY --from=api-builder /var/www/api /var/www/api

# Copy Nginx and Supervisor configs
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# entrypoint のコピー・権限設定・所有者変更を1レイヤーにまとめる
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && chown -R www-data:www-data \
        /var/www/api/storage \
        /var/www/api/bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD wget -q -O- http://localhost/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
