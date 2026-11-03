FROM php:8.3-fpm-alpine

# Instalar dependencias del sistema
RUN apk add --no-cache \
    libzip-dev \
    libpng-dev \
    zip \
    unzip \
    curl \
    git

# Instalar extensiones de PHP (MySQL + zip/gd para leer Excel con PhpSpreadsheet)
RUN docker-php-ext-install pdo pdo_mysql zip gd

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]