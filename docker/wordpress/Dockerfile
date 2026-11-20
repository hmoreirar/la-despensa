FROM wordpress:7.1.5-php8.4-apache

# Iguala www-data con el UID/GID del host para que Apache y WP-CLI
# creen archivos editables desde el host en el bind mount wp-content.
ARG HOST_UID=1000
ARG HOST_GID=1000

RUN groupmod -o -g "${HOST_GID}" www-data \
    && usermod -o -u "${HOST_UID}" -g www-data www-data \
    && chown -R www-data:www-data /var/www/html
