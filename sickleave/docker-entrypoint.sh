#!/usr/bin/env bash
set -e

# Force correct MPM
a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true

# Force Apache to listen on Railway PORT
PORT_TO_USE=${PORT:-80}

sed -i "s/Listen 80/Listen ${PORT_TO_USE}/" /etc/apache2/ports.conf || true
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT_TO_USE}>/" /etc/apache2/sites-available/000-default.conf || true

echo "Apache listening on port ${PORT_TO_USE}"

exec apache2-foreground
