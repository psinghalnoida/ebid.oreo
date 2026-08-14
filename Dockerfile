# eBid Hub (AdwitiX) — main PHP application container.
#
# Standard CodeIgniter 4 app, served by Apache/mod_php. Document root is
# public/ per SETUP.md's "Production web server" section — everything
# else (app/, system/, .env) stays outside the servable tree.
#
# Built for Cloud Run: listens on a fixed port 8080 (Cloud Run's own
# default), no dynamic $PORT parsing needed since the deploy step below
# always sets --port 8080 to match.
#
# Base images pulled via mirror.gcr.io (Google's own Docker Hub mirror)
# rather than docker.io directly — avoids Docker Hub's anonymous pull
# rate limit inside CI, a real production concern independent of any
# one build environment's network policy.

FROM mirror.gcr.io/library/php:8.2-apache

# BR/PR reference: matches composer.json's declared "php": "^8.2" exactly
# rather than floating to whatever PHP the base image latest tag carries,
# so the deployed runtime is the same major/minor the app was built and
# tested against locally.

RUN apt-get update && apt-get install -y --no-install-recommends \
        default-libmysqlclient-dev libzip-dev libicu-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
        unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli gd intl zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Composer itself only needs its binary, not a whole separate PHP
# environment — copying it into this image (rather than `composer
# install` running inside the composer:2 image, which does NOT have
# ext-intl/pdo_mysql/etc. installed) means composer's platform-
# requirement check runs against the actual runtime the app ships in,
# not a bare, different PHP. Caught by a real local build: running
# `composer install` in a separate composer:2 stage failed outright
# ("ext-intl -> it is missing from your system") even though the final
# image genuinely has intl — the two stages just weren't the same PHP.
COPY --from=mirror.gcr.io/library/composer:2 /usr/bin/composer /usr/bin/composer

# Cloud Run always injects PORT (default 8080); pin Apache to it directly
# rather than substituting at container start, since the Cloud Run
# services in the deploy workflow are always deployed with --port 8080.
RUN sed -ri 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri 's/:80>/:8080>/' /etc/apache2/sites-available/000-default.conf
EXPOSE 8080

# public/ as the document root, with AllowOverride so public/.htaccess's
# rewrite-to-index.php rules (CodeIgniter's front-controller pattern)
# actually take effect.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
    && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Dependencies before the rest of the app so a source-only change
# doesn't invalidate composer's layer cache.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader --prefer-dist

COPY . .

# writable/ must survive as an actual writable directory (logs, cache,
# session files, uploaded media under writable/uploads) — CodeIgniter's
# own convention, not something Apache creates on its own.
RUN chown -R www-data:www-data /var/www/html/writable \
    && chmod -R 775 /var/www/html/writable

CMD ["apache2-foreground"]
