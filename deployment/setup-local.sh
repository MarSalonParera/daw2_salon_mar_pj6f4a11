#!/bin/sh
# Debian/Ubuntu Apache setup. Run from a terminal: sudo sh deployment/setup-local.sh
set -eu
if [ "$(id -u)" -ne 0 ]; then
    echo 'Run with sudo: sudo sh deployment/setup-local.sh' >&2
    exit 1
fi
project_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
if [ "$project_root" != '/var/www/html/restaurantOrderApp' ]; then
    echo 'Adapt the Apache configuration paths before using another project root.' >&2
    exit 1
fi
command -v php >/dev/null
command -v openssl >/dev/null
a2enmod ssl headers rewrite
install -d -m 700 /etc/apache2/ssl
if [ ! -f /etc/apache2/ssl/order-management-local.crt ] || [ ! -f /etc/apache2/ssl/order-management-local.key ]; then
    openssl req -x509 -newkey rsa:3072 -sha256 -nodes -days 365 \
        -keyout /etc/apache2/ssl/order-management-local.key \
        -out /etc/apache2/ssl/order-management-local.crt \
        -subj '/CN=localhost' -addext 'subjectAltName=DNS:localhost,IP:127.0.0.1'
    chmod 600 /etc/apache2/ssl/order-management-local.key
fi
install -m 644 "$project_root/deployment/apache-local-ssl.conf" /etc/apache2/sites-available/order-management-local.conf
install -m 644 "$project_root/deployment/apache-local-access.conf" /etc/apache2/conf-available/order-management-local-access.conf
# PHP can write orders, but cannot modify the fixed user fixture.
chgrp www-data "$project_root/orders"
chmod 2775 "$project_root/orders"
# Initialize storage during setup; request handlers never recreate missing files.
if [ ! -e "$project_root/orders/orders.json" ]; then
    printf '[]\n' > "$project_root/orders/orders.json"
fi
if [ -f "$project_root/orders/orders.json" ]; then
    chgrp www-data "$project_root/orders/orders.json"
    chmod 660 "$project_root/orders/orders.json"
fi
chmod 644 "$project_root/users/users.json"
a2enconf order-management-local-access
a2ensite order-management-local
apache2ctl configtest
systemctl reload apache2
echo 'Ready: https://localhost/restaurantOrderApp/'
echo 'The local certificate is self-signed. Verify and trust it locally for browser testing.'
echo 'Use a trusted certificate for a production domain.'
