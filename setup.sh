#!/bin/bash

# ====================================
# Bird Project - Setup Script
# ====================================

set -e

echo "🐦 Bird Project Setup Starting..."

# Check Docker
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker Desktop and try again."
    exit 1
fi

echo "✅ Docker is running"

# Create apps directory if not exists
mkdir -p apps

# Setup Laravel API
echo "📦 Setting up Laravel API..."
cd apps

if [ ! -d "api" ]; then
    echo "Creating Laravel project with Sail..."
    curl -s "https://laravel.build/api?with=pgsql,redis" | bash
    
    cd api
    
    # Install additional dependencies
    ./vendor/bin/sail composer require \
        laravel/octane \
        laravel/telescope \
        tymon/jwt-auth \
        predis/predis
    
    ./vendor/bin/sail composer require --dev \
        pestphp/pest \
        pestphp/pest-plugin-laravel \
        larastan/larastan
    
    # Initialize Pest
    ./vendor/bin/sail artisan pest:install
    
    cd ../..
else
    echo "✅ Laravel API already exists"
fi

# Setup Next.js Web
echo "📦 Setting up Next.js Web..."
cd apps

if [ ! -d "web" ]; then
    pnpm create next-app@latest web \
        --typescript \
        --tailwind \
        --app \
        --src-dir \
        --import-alias "@/*" \
        --no-turbopack
    
    cd web
    
    # Install dependencies
    pnpm add \
        maplibre-gl \
        react-map-gl \
        jotai \
        swr \
        @tanstack/react-query \
        date-fns \
        zod
    
    pnpm add -D \
        vitest \
        @testing-library/react \
        @testing-library/jest-dom \
        @playwright/test \
        storybook \
        @storybook/nextjs \
        @storybook/addon-essentials
    
    cd ../..
else
    echo "✅ Next.js Web already exists"
fi

# Copy environment variables
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "✅ Created .env file"
fi

# Install root dependencies
echo "📦 Installing root dependencies..."
pnpm install

echo ""
echo "✅ Setup Complete!"
echo ""
echo "Next steps:"
echo "  1. Review and update .env file"
echo "  2. Start Docker: docker-compose up -d"
echo "  3. Run migrations: docker exec -it bird-api php artisan migrate"
echo "  4. Start development: pnpm dev"
echo ""
