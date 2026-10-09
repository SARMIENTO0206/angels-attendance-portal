FROM php:8.2-apache

COPY angels-portal/ /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
 && chmod +x /usr/local/bin/docker-entrypoint.sh \
 && printf 'upload_max_filesize=4M\npost_max_size=8M\n' > /usr/local/etc/php/conf.d/uploads.ini

ENV DATA_DIR=/data
CMD ["docker-entrypoint.sh"]
