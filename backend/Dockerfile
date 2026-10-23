FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y libpq-dev libonig-dev libzip-dev git unzip \
    && docker-php-ext-install pdo_pgsql mbstring zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

EXPOSE 8000