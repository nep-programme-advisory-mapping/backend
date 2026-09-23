FROM php:8.2-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libonig-dev libxml2-dev libjpeg-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring exif pcntl bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend
COPY . .

RUN mkdir -p bootstrap/cache storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && chown -R www-data:www-data storage bootstrap/cache

RUN chmod +x docker/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php-fpm"]
