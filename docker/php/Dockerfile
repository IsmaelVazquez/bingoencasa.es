FROM php:8.5-fpm

# Instalador robusto de extensiones PHP (maneja libs del sistema, xdebug y
# versiones nuevas de PHP de forma fiable). Evita los fallos del compilado manual.
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions intl pdo_pgsql zip opcache mbstring xml xdebug

# Utilidades del sistema para Composer y el entrypoint
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip gettext-base \
    && rm -rf /var/lib/apt/lists/*


# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Usuario no-root
ARG PUID=1000
ARG PGID=1000
RUN groupadd -g "${PGID}" app \
    && useradd -u "${PUID}" -g app -m -s /bin/bash app \
    && mkdir -p /var/www/html \
    && chown -R app:app /var/www/html

# PHP-FPM con usuario app
RUN sed -i 's/^user = .*/user = app/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^group = .*/group = app/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's~^;*listen.owner = .*~listen.owner = app~' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's~^;*listen.group = .*~listen.group = app~' /usr/local/etc/php-fpm.d/www.conf

# Configuración PHP y Xdebug
ENV XDEBUG_MODE=debug,develop \
    XDEBUG_CLIENT_HOST=host.docker.internal \
    XDEBUG_CLIENT_PORT=9003 \
    PATH="/var/www/html/vendor/bin:/home/app/.composer/vendor/bin:${PATH}"

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini.template
COPY docker/php/entrypoint.sh /usr/local/bin/php-dev-entrypoint

RUN chmod +x /usr/local/bin/php-dev-entrypoint \
    && chown app:app /usr/local/etc/php/conf.d/app.ini /usr/local/etc/php/conf.d/xdebug.ini.template \
    && chown -R app:app /usr/local/etc/php/conf.d

WORKDIR /var/www/html

USER app

ENTRYPOINT ["php-dev-entrypoint"]
CMD ["php-fpm"]
