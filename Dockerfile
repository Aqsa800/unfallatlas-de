# syntax=docker/dockerfile:1
FROM node:22-bookworm-slim AS frontend
WORKDIR /frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM php:8.4-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev \
    libfreetype6-dev libonig-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_pgsql mbstring gd zip dom simplexml xml xmlreader xmlwriter opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY backend/ ./
RUN rm -f .env bootstrap/cache/config.php \
    && mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=frontend /frontend/dist/ ./public/
RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    'DocumentRoot /var/www/html/public' \
    '<Directory /var/www/html/public>' \
    'Options -Indexes' \
    'AllowOverride All' \
    'Require all granted' \
    'DirectoryIndex index.html index.php' \
    '</Directory>' \
    '</VirtualHost>' > /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
    '<IfModule mod_rewrite.c>' \
    'RewriteEngine On' \
    'RewriteRule ^(?:api(?:/|$)|up$) index.php [L]' \
    'RewriteCond %{REQUEST_FILENAME} !-f' \
    'RewriteCond %{REQUEST_FILENAME} !-d' \
    'RewriteRule ^ index.html [L]' \
    '</IfModule>' > public/.htaccess \
    && printf '%s\n' 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && printf '%s\n' 'expose_php=Off' 'memory_limit=256M' > /usr/local/etc/php/conf.d/production.ini
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr \
    SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync
EXPOSE 80
CMD ["apache2-foreground"]
