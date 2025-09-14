#!/bin/bash

set -e

# Function to wait for database
wait_for_db() {
    echo "Waiting for database connection..."
    while ! pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE"; do
        echo "Database is unavailable - sleeping"
        sleep 2
    done
    echo "Database is ready!"
}

# Function to wait for Redis
wait_for_redis() {
    echo "Waiting for Redis connection..."
    while ! redis-cli -h "$REDIS_HOST" -p "$REDIS_PORT" ping > /dev/null 2>&1; do
        echo "Redis is unavailable - sleeping"
        sleep 2
    done
    echo "Redis is ready!"
}

# Wait for services
if [ "$DB_CONNECTION" = "pgsql" ]; then
    wait_for_db
fi

if [ "$CACHE_DRIVER" = "redis" ] || [ "$SESSION_DRIVER" = "redis" ] || [ "$QUEUE_CONNECTION" = "redis" ]; then
    wait_for_redis
fi

# Generate application key if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Clear and cache configuration
echo "Optimizing application..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
if [ "$APP_ENV" = "production" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
    
    # Seed essential data if needed
    if [ "$SEED_DATABASE" = "true" ]; then
        echo "Seeding database..."
        php artisan db:seed --force
    fi
    
    # Generate API documentation
    echo "Generating API documentation..."
    php artisan scramble:export
fi

# Create storage link
php artisan storage:link

# Set proper permissions
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Start cron service for scheduled tasks
if [ "$1" = "scheduler" ]; then
    echo "Starting scheduler..."
    service cron start
    # Add Laravel scheduler to crontab
    echo "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" | crontab -
    # Keep container running
    tail -f /dev/null
elif [ "$1" = "queue" ]; then
    echo "Starting queue worker..."
    exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --verbose
elif [ "$1" = "horizon" ]; then
    echo "Starting Laravel Horizon..."
    exec php artisan horizon
else
    echo "Starting PHP-FPM..."
    exec php-fpm
fi