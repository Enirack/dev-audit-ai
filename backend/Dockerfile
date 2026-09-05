FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql intl opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000

CMD ["sh", "-c", "php bin/console doctrine:migrations:migrate --no-interaction && php -S 0.0.0.0:8000 -t public"]
