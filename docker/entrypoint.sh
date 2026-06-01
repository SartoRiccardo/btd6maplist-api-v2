#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
php artisan migrate --force

exec supervisord -c /etc/supervisor.d/supervisord.ini
