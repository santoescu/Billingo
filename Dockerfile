###############################################
# Imagen PHP 8.2 CLI
###############################################
FROM php:8.2-cli

###############################################
# Dependencias del sistema
###############################################
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    nodejs \
    npm \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libssl-dev \
    libzstd-dev \
    pkg-config \
    && docker-php-ext-install \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        gd

###############################################
# MongoDB
###############################################
RUN pecl install mongodb && \
    echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongodb.ini

###############################################
# Composer
###############################################
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

###############################################
# Directorio
###############################################
WORKDIR /var/www/html

###############################################
# Copiar proyecto
###############################################
COPY . .

###############################################
# Instalar Composer
###############################################
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

###############################################
# Instalar Node
###############################################
RUN npm install

###############################################
# Compilar Vite
###############################################
RUN npm run build

###############################################
# Optimizar Laravel
###############################################
RUN php artisan config:cache || true
RUN php artisan route:cache || true
RUN php artisan view:cache || true

###############################################
# Permisos
###############################################
RUN chmod -R 775 storage bootstrap/cache

###############################################
# Puerto
###############################################
EXPOSE 8080

###############################################
# Ejecutar Laravel
###############################################
CMD ["sh","-c","php -S 0.0.0.0:${PORT:-8080} -t public"]
