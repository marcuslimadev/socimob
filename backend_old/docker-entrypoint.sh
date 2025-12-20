#!/bin/bash
set -e

echo "🔄 Running database migrations..."
php artisan migrate --force

echo "🚀 Starting Apache..."
exec apache2-foreground
