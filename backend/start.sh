#!/bin/bash
set -e

echo "=== Starting WallBet ==="
echo "=== Running migrations ==="
php artisan migrate --force 2>&1 || echo "!!! Migration failed !!!"

echo "=== Running seeder ==="
php artisan db:seed --force 2>&1 || echo "!!! Seed failed !!!"

echo "=== Caching config ==="
php artisan config:cache 2>&1 || true
php artisan route:cache 2>&1 || true

echo "=== Starting server on port ${PORT:-8080} ==="
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
