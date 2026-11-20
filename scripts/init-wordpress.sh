#!/usr/bin/env bash
set -Eeuo pipefail

cd /var/www/html

WP_URL="${WP_URL:?Falta WP_URL}"
WP_TITLE="${WP_TITLE:-La Despensa}"
WP_ADMIN_USER="${WP_ADMIN_USER:?Falta WP_ADMIN_USER}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:?Falta WP_ADMIN_PASSWORD}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"

if ! wp core is-installed 2>/dev/null; then
  echo "Instalando WordPress..."
  wp core install \
    --url="$WP_URL" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
else
  echo "WordPress ya esta instalado. Se actualiza configuracion."
fi

# Idioma del sitio y del panel
if ! wp language core is-installed es_CL 2>/dev/null; then
  wp language core install es_CL
fi
if [ "$(wp option get WPLANG)" != "es_CL" ]; then
  wp site switch-language es_CL
fi

# Regionalizacion
wp option update timezone_string America/Santiago
wp option update date_format 'd-m-Y'
wp option update time_format 'H:i'
wp option update start_of_week 1

# Identidad del sitio
wp option update blogname "$WP_TITLE"
wp option update blogdescription 'E-commerce gastronómico desarrollado con WordPress y WooCommerce.'
wp option update home "$WP_URL"
wp option update siteurl "$WP_URL"

# Enlaces permanentes
wp rewrite structure '/%postname%/'
wp rewrite flush --hard

echo "WordPress listo en $WP_URL"
