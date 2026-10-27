# Stajnia Hrabhill — EasyPanel (App Service → Dockerfile)
FROM php:8.3-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl git unzip libicu-dev \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd intl opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/symfony.conf \
    && a2enconf symfony

WORKDIR /var/www/html

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && composer dump-autoload --optimize --classmap-authoritative \
    && php bin/console cache:clear --env=prod --no-warmup || true

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    TRUSTED_PROXIES=REMOTE_ADDR \
    PORT=80

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
  CMD curl -fsS "http://127.0.0.1:${PORT:-80}/" >/dev/null || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
