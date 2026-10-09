#!/bin/sh
set -e

rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
[ -e /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod mpm_prefork >/dev/null

PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Persistent data (Railway Volume mounted at /data)
mkdir -p "${DATA_DIR}/uploads/employees"
rm -rf /var/www/html/uploads/employees
ln -s "${DATA_DIR}/uploads/employees" /var/www/html/uploads/employees
chown -R www-data:www-data "${DATA_DIR}"

exec apache2-foreground
