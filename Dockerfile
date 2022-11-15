
# FROM alpine:3.16

# # ADD lighttpd.conf /etc/lighttpd/lighttpd.conf
# RUN adduser www-data -G www-data -H -s /bin/false -D

# EXPOSE 80
# VOLUME /var/www

# ENTRYPOINT ["/usr/sbin/lighttpd", "-D", "-f", "/etc/lighttpd/lighttpd.conf"]

# RUN apk --update add \
#     lighttpd \
#     php81-common \
#     php81-gd \
#     php81-pecl-mongodb \
#     mongodb-tools \
#     php81-cgi \
#     php81-exif \
#     php81-ffi \
#     fcgi \
#     vips && \
#     rm -rf /var/cache/apk/*

# ADD deploy/fotodirbrowser/lighttpd.conf /etc/lighttpd/lighttpd.conf
# ADD deploy/fotodirbrowser/php.ini /etc/php81/php.ini

# RUN mkdir -p /run/lighttpd/ && \
#     chown www-data. /run/lighttpd/

# VOLUME /data
# VOLUME /thumbs

# COPY src/ /var/www/

# CMD php-fpm -D && lighttpd -D -f /etc/lighttpd/lighttpd.conf 2>&1

FROM composer:2.4 AS build
WORKDIR /app

COPY src/composer.json .
COPY src/composer.lock .
RUN composer install --no-dev --no-scripts --ignore-platform-reqs

COPY src/ .
RUN composer dumpautoload --optimize

FROM php:8.1-fpm
WORKDIR /app

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
     install-php-extensions ffi mongodb vips exif && \
     echo "ffi.enable=true" >> /usr/local/etc/php/conf.d/docker-php-ext-ffi.ini && \
     echo "ffi.enable=true" >> /usr/local/etc/php/php.ini-production && \
     echo "memory_limit=1024M" >> /usr/local/etc/php/php.ini-production 
     
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN apt update && \
    apt install -y \
       #ffmpeg
       ffmpeg && \
       ###adds ssl support to mongodb
       ##libcurl4-openssl-dev pkg-config libssl-dev && \ 
       apt purge -y --auto-remove

# # #read exif data
# # RUN docker-php-ext-install \
# #        exif \
# #        #vips?
# #        ffi && \
# #      docker-php-ext-enable exif ffi

# # # work with mongodb
# # RUN pecl install mongodb && \
# #     docker-php-ext-enable mongodb

# RUN apt update && \
#     apt install -y \
#     libicu-dev=67.1-7 \
#     libgd-dev=2.3.0-2 \
#     libonig-dev=6.9.6-1.1 \
#     unzip=6.0-26 && \
#     apt purge -y --auto-remove

# RUN docker-php-ext-install \
#     exif \
#     gd \
#     intl \
#     mbstring \
#     mysqli \
#     opcache \
#     pdo_mysql \
#     sockets

COPY --from=build /app /app
