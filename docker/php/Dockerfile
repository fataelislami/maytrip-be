FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        bash \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        gd \
        exif \
        zip \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP runtime tuning for a small VPS
COPY docker/php/php.ini /usr/local/etc/php/conf.d/maytrip.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-maytrip.conf

# Run as the same UID as the host user (override on `docker compose build` if needed)
ARG WWWUSER=1000
ARG WWWGROUP=1000
RUN if [ "$WWWUSER" != "82" ]; then \
        deluser www-data 2>/dev/null || true ; \
        delgroup www-data 2>/dev/null || true ; \
        addgroup -g $WWWGROUP -S www-data && \
        adduser -u $WWWUSER -S -G www-data www-data ; \
    fi

WORKDIR /var/www/html

# Healthcheck: php-fpm responds on its socket
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s \
    CMD php -r "exit(0);" || exit 1

EXPOSE 9000
CMD ["php-fpm"]
