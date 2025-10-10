FROM composer:2.4 AS build
WORKDIR /app

COPY src/composer.json .
COPY src/composer.lock .
RUN composer install --no-dev --no-scripts --ignore-platform-reqs

COPY src/ .
RUN composer dumpautoload --optimize

FROM php:8.2-fpm
WORKDIR /app

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
     install-php-extensions ffi mongodb vips exif inotify && \
     echo "ffi.enable=true" >> /usr/local/etc/php/conf.d/docker-php-ext-ffi.ini && \
     echo "ffi.enable=true" >> /usr/local/etc/php/php.ini-production && \
     echo "memory_limit=1024M" >> /usr/local/etc/php/php.ini-production 
     
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN apt update && \
    apt install -y \
       ffmpeg && \
       apt purge -y --auto-remove

COPY --from=build /app /app
