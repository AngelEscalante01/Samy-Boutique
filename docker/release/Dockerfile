FROM composer:2.8.9 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-dev --ignore-platform-reqs

FROM node:22.16.0-bookworm-slim AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY --from=vendor /build/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
COPY vite.config.js tailwind.config.js postcss.config.js ./
ARG VITE_PRODUCT_IMAGES_URL
ARG VITE_FIREBASE_API_KEY
ARG VITE_FIREBASE_AUTH_DOMAIN
ARG VITE_FIREBASE_PROJECT_ID
ARG VITE_FIREBASE_STORAGE_BUCKET
ARG VITE_FIREBASE_MESSAGING_SENDER_ID
ARG VITE_FIREBASE_APP_ID
ARG VITE_FIREBASE_VAPID_KEY
ENV APP_URL=https://sistema.samyboutique.com.mx
RUN npm run build

FROM php:8.2.28-fpm-bookworm AS app
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql pcntl bcmath zip gd opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=vendor /usr/bin/composer /usr/local/bin/composer
RUN docker-php-ext-install exif
WORKDIR /app
COPY composer.json composer.lock ./
COPY --from=vendor /build/vendor ./vendor
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database/migrations ./database/migrations
COPY database/seeders ./database/seeders
COPY resources/views ./resources/views
COPY resources/import-templates ./resources/import-templates
COPY resources/demo-showcase ./resources/demo-showcase
COPY routes ./routes
COPY public ./public
COPY artisan ./artisan
COPY --from=assets /build/public/build ./public/build
COPY --chmod=644 docker/release/php.ini /usr/local/etc/php/conf.d/zz-samy.ini
COPY --chmod=644 docker/release/pool.conf /usr/local/etc/php-fpm.d/zz-samy.conf
COPY docker/release/entrypoint.sh /usr/local/bin/samy-release
RUN mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --no-scripts --classmap-authoritative \
    && composer check-platform-reqs --no-dev \
    && ln -s /app/storage/app/public public/storage \
    && chmod -R a+rX /app \
    && chmod 755 /usr/local/bin/samy-release \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
ENTRYPOINT ["samy-release"]
CMD ["php-fpm", "-F"]

FROM nginx:1.27-alpine AS gateway
COPY --from=app /app/public /app/public
COPY --chmod=644 docker/release/nginx.conf /etc/nginx/conf.d/default.conf
