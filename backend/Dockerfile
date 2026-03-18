FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy application code
COPY . .

# Run post-install scripts (no env needed)
RUN composer run-script post-autoload-dump || true

# Create required storage directories
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Start script: migrate, seed, cache, serve
CMD echo "=== Starting WallBet ===" && \
    echo "DB_URL: ${DB_URL:0:30}..." && \
    echo "=== Running migrations ===" && \
    php artisan migrate --force 2>&1 || echo "!!! Migration failed !!!" && \
    echo "=== Running seeder ===" && \
    php artisan db:seed --force 2>&1 || echo "!!! Seed failed !!!" && \
    echo "=== Caching config ===" && \
    php artisan config:cache 2>&1 || true && \
    php artisan route:cache 2>&1 || true && \
    echo "=== Starting server on port ${PORT:-8080} ===" && \
    php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
