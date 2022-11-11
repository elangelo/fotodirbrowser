
FROM alpine:3.16

# ADD lighttpd.conf /etc/lighttpd/lighttpd.conf
RUN adduser www-data -G www-data -H -s /bin/false -D

EXPOSE 80
VOLUME /var/www

ENTRYPOINT ["/usr/sbin/lighttpd", "-D", "-f", "/etc/lighttpd/lighttpd.conf"]

RUN apk --update add \
    lighttpd \
    php81-common \
    php81-gd \
    php81-pecl-mongodb \
    mongodb-tools \
    php81-cgi \
    php81-exif \
    php81-ffi \
    fcgi \
    vips && \
    rm -rf /var/cache/apk/*

ADD deploy/fotodirbrowser/lighttpd.conf /etc/lighttpd/lighttpd.conf
ADD deploy/fotodirbrowser/php.ini /etc/php81/php.ini

RUN mkdir -p /run/lighttpd/ && \
    chown www-data. /run/lighttpd/

VOLUME /data
VOLUME /thumbs

COPY src/ /var/www/

CMD php-fpm -D && lighttpd -D -f /etc/lighttpd/lighttpd.conf 2>&1