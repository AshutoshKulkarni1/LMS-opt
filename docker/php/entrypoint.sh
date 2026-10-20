#!/bin/sh
set -e

# Dynamically configure Apache port if provided by Railway/Render ($PORT)
# Only replace the Listen directive and VirtualHost port - NOT all occurrences of "80"
# to avoid corrupting unrelated numeric values in the Apache config.
if [ -n "$PORT" ]; then
    sed -i "s/^Listen 80$/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/*.conf
fi

exec apache2-foreground
