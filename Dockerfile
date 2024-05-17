# build application runtime, image page: <https://hub.docker.com/_/php>
FROM php:8.3.3-alpine as runtime

# install composer, image page: <https://hub.docker.com/_/composer>
COPY --from=composer:2.7.1 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME="/tmp/composer"

# use directory with application sources by default
WORKDIR /app

# "fix" composer issue "Cannot create cache directory /tmp/composer/cache/..." for docker-compose usage
RUN set -x \
    mkdir ${COMPOSER_HOME}/cache \
    chmod -R 777 ${COMPOSER_HOME}/cache

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && apk add --update linux-headers \
    && pecl install xdebug-3.3.2 \
    && docker-php-ext-enable xdebug \
    && apk del -f .build-deps

COPY ./conf.d /usr/local/etc/php/conf.d

# unset default image entrypoint
ENTRYPOINT []
