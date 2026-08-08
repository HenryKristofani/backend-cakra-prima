FROM php:8.3-fpm-alpine AS base

# Install dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    postgresql-dev \
    oniguruma-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --optimize-autoloader --no-dev --no-interaction

# Node untuk build Vite (Blade admin)
RUN apk add --no-cache nodejs npm \
    && npm install && npm run build

RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8080

CMD ["/start.sh"]