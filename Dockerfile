# ── Stage 1: Composer — fetch Filament CSS for the Vite build ───────────────
# We only need vendor/filament/ (a few MB of CSS) so Tailwind can resolve the
# @import in resources/css/filament/admin/theme.css. Without the real Filament
# theme.css the Vite output is an empty CSS file → zero panel styling.
# --ignore-platform-reqs lets composer run in the generic composer image
# regardless of PHP version; we are only downloading files, not executing PHP.
FROM composer:latest AS composer-builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
      --no-dev \
      --no-autoloader \
      --no-scripts \
      --no-interaction \
      --ignore-platform-reqs

# ── Stage 2: Node.js asset builder ─────────────────────────────────────────
# Node.js is ONLY needed to compile Vite/Filament assets.
# Keeping it in a separate stage removes ~80 MB from the production image.
FROM node:20-alpine AS node-builder
WORKDIR /app
# Cache-friendly: only re-runs npm ci when package-lock.json changes
COPY package.json package-lock.json ./
RUN npm ci --prefer-offline
COPY . .
# Provide the REAL Filament vendor CSS so Tailwind/Vite compiles a complete
# panel theme. Filament's theme.css has @import chains across filament/*
# packages (forms, tables, actions, etc.) so we copy the full vendor/filament
# directory (~a few MB). No stub needed anymore.
COPY --from=composer-builder /app/vendor/filament ./vendor/filament
RUN npm run build && npm cache clean --force

# ── Stage 3: PHP production image ────────────────────────────────────────────
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
    libev-dev \
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
    pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    # ext-ev: libev-backed event loop used by Reverb's WebSocket server.
    # The default ReactPHP stream_select loop is O(n) per tick and is also
    # capped at 1024 fds by FD_SETSIZE — useless against the 10k nofile
    # ulimit set in docker-compose. ext-ev binds libev (epoll on Linux,
    # kqueue on BSD) which is O(1) per tick and scales to the full ulimit.
    # ReactPHP loop priority: ev > uv > event > stream_select, so ev gets
    # picked up automatically with no app code change.
    # NOTE: chose ext-ev over ext-uv because the PECL `uv` package has been
    # unmaintained since 2022 (v0.3.0) and no longer compiles on PHP 8.4.
    # `ev` 1.1.5 (2023) supports PHP 8+ and is actively maintained.
    && pecl install ev \
    && docker-php-ext-enable ev \
    && apk del $PHPIZE_DEPS shadow \
    && rm -rf /tmp/* /var/cache/apk/*

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuration de PHP pour la production
COPY .docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/php.ini
COPY .docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Définition du répertoire de travail
WORKDIR /var/www

# Création de l'utilisateur pour éviter les problèmes de permissions
# Alpine does not ship usermod/groupmod (shadow pkg) — use busybox builtins instead
RUN deluser www-data 2>/dev/null; delgroup www-data 2>/dev/null; \
    addgroup -g 1000 -S www-data && adduser -u 1000 -D -S -H -G www-data www-data

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
