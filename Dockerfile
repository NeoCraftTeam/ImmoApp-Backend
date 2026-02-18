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
    libwebp-dev \
    libavif-dev \
    jpegoptim \
    optipng \
    pngquant \
    gifsicle \
    libwebp-tools \
    libavif-apps \
    oniguruma-dev \
    gettext-dev \
    shadow \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j$(nproc) \
    gd \
    bcmath \
    intl \
    pdo_pgsql \
    mbstring \
    zip \
    opcache \
    exif \
    gettext \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/*

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuration de PHP pour la production
COPY .docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/php.ini

# Définition du répertoire de travail
WORKDIR /var/www

# Création de l'utilisateur pour éviter les problèmes de permissions
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# On copie le code (plus tard, dans le pipeline, on pourra optimiser cela)
COPY . .

# Création des répertoires storage requis par artisan package:discover
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && cp .env.example .env 2>/dev/null || true

# Installation des dépendances (SANS les tests/dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Attribution des permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Exposition du port FPM
EXPOSE 9000

# Health check — php-fpm responds to fcgi status
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD php-fpm -t 2>/dev/null || exit 1

CMD ["php-fpm"]
