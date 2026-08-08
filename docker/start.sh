#!/bin/sh
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

supervisord -c /etc/supervisord.conf