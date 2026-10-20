#!/bin/sh
set -e

# Configure Apache to listen on $PORT if provided by Railway/Render/Cloud
if [ -n "$PORT" ]; then
    sed -i "s/80/$PORT/g" /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf
fi

exec apache2-foreground
