FROM php:8.3-fpm-alpine

# Instalar dependencias del sistema y de PostgreSQL
RUN apk add --no-cache \
    libpq-dev \
    zip \
    unzip \
    curl \
    git

# Instalar extensiones de PHP (PostgreSQL)
RUN docker-php-ext-install pdo pdo_pgsql

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]