# Imagen base PHP 8.2
###############################################
# STAGE 1: Construcción de assets con Node
###############################################
FROM node:18 AS build-assets

WORKDIR /app

# Copiar solo archivos necesarios para NPM
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources

RUN npm install
RUN npm run build



###############################################
# STAGE 2: Imagen PHP + Laravel
###############################################
FROM php:8.2-fpm

# Instalar dependencias del sistema
# Dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libssl-dev \
    libzstd-dev \
    pkg-config \
    && docker-php-ext-install mbstring zip exif pcntl bcmath gd

# Instalar la extensión MongoDB
# Instalar extensión MongoDB
RUN pecl install mongodb && \
    echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongodb.ini

# Instalar Node.js 18 para Vite
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs

# Instalar Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Directorio de la app
WORKDIR /var/www/html

# Copiar archivos del proyecto
# Copiar resto del proyecto
COPY . .

# Copiar assets construidos desde el Stage 1
COPY --from=build-assets /app/public/build ./public/build

# Instalar dependencias de Laravel
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Construir Vite
RUN npm install && npm run build

# Permisos
RUN chmod -R 777 storage bootstrap/cache

# Puerto de Cloud Run
# Cloud Run escucha en el 8080
EXPOSE 8080

# Servir Laravel
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t public"]

