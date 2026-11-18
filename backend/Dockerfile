FROM php:8.3-fpm-alpine

# Instalar dependencias del sistema
RUN apk add --no-cache \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    zip \
    unzip \
    curl \
    git

# Instalar extensiones de PHP (MySQL + zip/gd para leer Excel con PhpSpreadsheet).
# GD con JPEG y WebP para optimizar las imágenes que se suben (portadas del blog)
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql zip gd

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]
