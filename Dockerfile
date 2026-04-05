# ── Stage 1: Node.js asset builder ─────────────────────────────────────────
# Node.js is ONLY needed to compile Vite/Filament assets.
# Keeping it in a separate stage removes ~80 MB from the production image.
FROM node:20-alpine AS node-builder
WORKDIR /app
# Cache-friendly: only re-runs npm ci when package-lock.json changes
COPY package.json package-lock.json ./
RUN npm ci --prefer-offline
COPY . .
# Stub the Filament vendor CSS so Tailwind/Vite can resolve the @import in
# resources/css/filament/admin/theme.css. The real Filament assets are
# published later by `php artisan filament:assets` in the PHP stage.
RUN mkdir -p vendor/filament/filament/resources/css \
    && touch vendor/filament/filament/resources/css/theme.css
RUN npm run build && npm cache clean --force

# ── Stage 2: PHP production image ────────────────────────────────────────────
FROM php:8.4-fpm-alpine

# Installation des dépendances système et extensions PHP nécessaires
RUN apk add --no-cache \
    bash \
    curl \
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
    perl \
    perl-image-exiftool \
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
    && apk del $PHPIZE_DEPS shadow \
    && rm -rf /tmp/* /var/cache/apk/*

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuration de PHP pour la production
COPY .docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/php.ini

# Définition du répertoire de travail
WORKDIR /var/www

# Création de l'utilisateur pour éviter les problèmes de permissions
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# ── Cache-friendly dependency installation ───────────────────────────────────
# Copying only composer.lock first means `composer install` is only re-run
# when dependencies actually change — NOT on every code commit.
COPY composer.json composer.lock ./
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/logs bootstrap/cache \
    && touch .env \
    && composer install --no-dev --no-autoloader --no-scripts --no-interaction \
    && rm -rf /root/.composer /tmp/composer*

# ── Copy full application codebase ───────────────────────────────────────────
COPY . .

# ── Copy Vite-built assets from the Node stage (no Node.js in final image) ──
COPY --from=node-builder /app/public/build ./public/build

# ── Regenerate autoloader with full classmap + publish static assets ─────────
# Using .env.example as a minimal env so artisan can boot without a real DB.
# --no-scripts was used above; we now run the static (no-DB) post-install tasks
# explicitly so they are baked into the image and never needed at deploy time.
RUN cp .env.example .env \
    && composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi 2>/dev/null || true \
    && php artisan vendor:publish --force --tag=livewire:assets --ansi --no-interaction 2>/dev/null || true \
    && php artisan filament:assets 2>/dev/null || true \
    && php scripts/patch-webpush.php 2>/dev/null || true \
    && rm -f .env \
    && find vendor -type d -name '.git' -prune -exec rm -rf {} + 2>/dev/null || true \
    && find vendor -type d \( -name 'tests' -o -name 'Tests' -o -name 'test' -o -name 'Test' \) -prune -exec rm -rf {} + 2>/dev/null || true \
    && find vendor -type d \( -name 'docs' -o -name 'doc' -o -name 'examples' -o -name 'example' \) -prune -exec rm -rf {} + 2>/dev/null || true \
    && find vendor -name '*.md' -delete 2>/dev/null || true \
    && find vendor -name 'CHANGELOG*' -delete 2>/dev/null || true \
    && find vendor -name 'phpunit*' -delete 2>/dev/null || true

# ── Set permissions ───────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Exposition du port FPM
EXPOSE 9000

# Healthcheck: verify the PHP-FPM master process is running.
# `php-fpm -t` only validates config; pgrep confirms the daemon is actually up.
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD pgrep -f 'php-fpm: master' > /dev/null 2>&1 || exit 1

CMD ["php-fpm"]
