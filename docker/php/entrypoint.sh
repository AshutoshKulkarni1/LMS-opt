#!/bin/sh
set -e

# Dynamically configure Apache port if provided by Railway/Render ($PORT)
if [ -n "$PORT" ]; then
    sed -i "s/80/$PORT/g" /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf
fi

exec apache2-foreground
