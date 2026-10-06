# Imagem opcional (Docker não é obrigatório para desenvolver). Ver docs/INSTALACAO.md.
FROM php:7.4-fpm-alpine AS base
RUN apk add --no-cache git unzip libzip-dev icu-dev linux-headers $PHPIZE_DEPS \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql pcntl zip intl opcache \
 && pecl install redis-5.3.7 && docker-php-ext-enable redis \
 && apk del $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

FROM base AS app
COPY . .
COPY --from=assets /app/public/build public/build
RUN composer install --no-dev --optimize-autoloader --no-interaction \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
