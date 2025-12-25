# Image de base PHP 8.4 FPM (Légère et rapide)
FROM php:8.4-fpm-alpine

# Installation des dépendances système et extensions PHP nécessaires
RUN apk add --no-cache \
    bash \
    curl \
    git \
    libpng-dev \
    libzip-dev \
    zlib-dev \
    icu-dev \
    postgresql-dev \
    postgresql-client \
    freetype-dev \
    libjpeg-turbo-dev \
    oniguruma-dev \
    gettext-dev \
    shadow

# Configuration et installation des extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    bcmath \
    intl \
    pdo_pgsql \
    mbstring \
    zip \
    opcache \
    exif \
    gettext

# Installation de Redis via PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuration d'Opcache pour la production
COPY .docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Définition du répertoire de travail
WORKDIR /var/www

# Création de l'utilisateur pour éviter les problèmes de permissions
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# On copie le code (plus tard, dans le pipeline, on pourra optimiser cela)
COPY . .

# Installation des dépendances (SANS les tests/dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Attribution des permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Exposition du port FPM
EXPOSE 9000

# Script d'entrée pour assurer les permissions au runtime si nécessaire
# CMD ["php-fpm"]
CMD ["php-fpm"]
