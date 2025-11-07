FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libonig-dev libpng-dev libxml2-dev \
    libpq-dev libssl-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-install pdo_mysql zip intl gd \
    && pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]

