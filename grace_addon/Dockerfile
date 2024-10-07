ARG BUILD_FROM
FROM $BUILD_FROM

WORKDIR /data

RUN apk -U upgrade && apk add --no-cache \
    curl \
    nginx \
    php83-fpm \
    php83-pdo \
    php83-pdo_sqlite \
    php83-session \
    sqlite \
    && ln -s /usr/sbin/php-fpm83 /usr/sbin/php-fpm \
    && addgroup -S php \
    && adduser -S -G php php \
    && rm -rf /var/cache/apk/* /etc/nginx/http.d/* /etc/php83/conf.d/* /etc/php83/php-fpm.d/* \
    && { \
        echo "extension=pdo.so"; \
        echo "extension=pdo_sqlite.so"; \
    } > /etc/php83/conf.d/20-pdo.ini \
    && { \
        echo "extension=session.so"; \
    } > /etc/php83/conf.d/21-session.ini

#RUN mkdir -p /grace_config && \
#    chown -R php:nginx /grace_config && \
#    chmod -R 660 /grace_config && \
#    touch /grace_config/grace.db && \
#    chown php:nginx /grace_config/grace.db

COPY files/general files/php83 /
RUN chown php:nginx /www/public/grace.db

ENTRYPOINT ["/init"]
