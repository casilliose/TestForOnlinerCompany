#!/bin/sh

cd /app && composer install
chmod 777 -R /app/log
php-fpm