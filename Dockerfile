FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends mariadb-server \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install mysqli

COPY . /var/www/html/
COPY deploy/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
 && mkdir -p /var/www/html/uploads/payments \
 && chown -R www-data:www-data /var/www/html/uploads

ENV DB_HOST=127.0.0.1 \
    DB_PORT=3306 \
    DB_USER=root \
    DB_PASS=sp_live_2026 \
    DB_NAME=star_publication \
    DB_SSL=0

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
