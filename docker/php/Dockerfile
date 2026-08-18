FROM php:8.4-cli

RUN apt-get update 

RUN apt-get install -y \
    libzip-dev \
    zip \
    && docker-php-ext-install zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

EXPOSE 8000

WORKDIR /var/www/html

CMD ["sh", "-c", "[ -d vendor ] || composer install --no-interaction; php -S 0.0.0.0:8000 -t public public/index.php"]