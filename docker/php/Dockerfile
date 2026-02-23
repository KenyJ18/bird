FROM php:8.3-apache

# 必要なシステムパッケージとPHP拡張のインストール
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    intl \
    zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Composerインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache DocumentRoot をCakePHPのwebrootに設定
COPY docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
