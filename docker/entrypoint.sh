#!/bin/sh
set -e

touch /var/www/html/storage/logs/laravel.log

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
php artisan migrate --force

php-fpm -D --nodaemonize 2>&1 &
exec nginx -g 'daemon off;'
