FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx supervisor postgresql-dev postgresql-client libwebp-dev libpng-dev libjpeg-turbo-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-webp --with-jpeg \
    && docker-php-ext-install pdo_pgsql pgsql pcntl opcache gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-custom.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-logging.conf
COPY docker/supervisord.ini /etc/supervisor.d/supervisord.ini
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]
