# syntax=docker/dockerfile:1
#
# EduVers — Movers Institute of Technology and Education
# Production image: PHP 8.3 + Apache, Composer dependencies, Vite-built assets.
# Used by Render (render.yaml) and for local testing (docker-compose.yml).

############################
# 1) PHP dependencies
############################
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --no-scripts --no-autoloader --ignore-platform-reqs

############################
# 2) Front-end assets (Vite + Tailwind)
############################
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js tailwind.config.js ./
COPY resources ./resources
COPY app ./app
COPY public ./public
# Tailwind also scans Laravel's pagination views (see @source in resources/css/app.css)
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
                   ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build && rm -f public/hot

############################
# 3) Runtime: PHP 8.3 + Apache
############################
FROM php:8.3-apache AS app

ENV APP_ENV=production \
    APP_DEBUG=false \
    PORT=8080 \
    COMPOSER_ALLOW_SUPERUSER=1

# PHP extensions (MySQL + PostgreSQL drivers, intl for number/file-size formatting)
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql pdo_pgsql intl bcmath zip opcache \
 && a2enmod rewrite headers \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && rm -rf /var/lib/apt/lists/* /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: listen on $PORT (Render sets it) and serve /public
COPY docker/apache/ports.conf /etc/apache2/ports.conf
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
# PHP limits (uploads up to 200 MB videos), OPcache
COPY docker/php/eduvers.ini "$PHP_INI_DIR/conf.d/zz-eduvers.ini"

WORKDIR /var/www/html

# Application code (see .dockerignore for what is excluded)
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public ./public

RUN composer dump-autoload --optimize --no-dev --no-interaction \
 && mkdir -p storage/app/private storage/app/public \
             storage/framework/cache/data storage/framework/sessions storage/framework/views \
             storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
