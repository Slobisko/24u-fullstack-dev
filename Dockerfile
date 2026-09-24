FROM composer:2 AS dependencies

WORKDIR /app
COPY composer.json ./
COPY app ./app
RUN composer install --no-interaction --prefer-dist --no-progress --no-dev

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev \
    && docker-php-ext-install intl \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/www

RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY --from=dependencies /app/vendor ./vendor
COPY . .

RUN mkdir -p temp log \
    && chown -R www-data:www-data temp log

EXPOSE 80
