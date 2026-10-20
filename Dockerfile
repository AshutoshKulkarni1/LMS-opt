# ============================================================
# LMS / Test Platform - PHP 8.2 CLI Built-in Server
# ============================================================
# Replaces PHP+Apache to eliminate recurring Apache MPM conflicts.
# PHP's built-in server (via router.php) is used for demo/staging.
#
# Verified extensions: pdo_mysql, mbstring, zip
# Routing: router.php handles clean URLs, static assets, API, POST
# Port: reads $PORT from Railway environment (default 8080)
# Schema: docker/mariadb/init.sql is present for auto-init on fresh DB
# ============================================================
FROM php:8.2-cli

# Install system libraries and PHP extensions
# pdo_mysql  — database access (required, Railway MySQL)
# mbstring   — multibyte string ops used throughout the app
# zip        — ZipArchive for any export/import features
# json, zlib, openssl are built into the cli image already
RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev \
        libzip-dev \
        libpng-dev \
        zip \
        unzip \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Build-time verification — fails the build if pdo_mysql is missing
RUN php -m | grep -q pdo_mysql \
    && php --ri pdo_mysql \
    && echo "✔ pdo_mysql OK"

# PHP runtime configuration
RUN { \
        echo 'error_reporting = E_ALL'; \
        echo 'log_errors = On'; \
        echo 'display_errors = Off'; \
        echo 'display_startup_errors = Off'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.use_strict_mode = 1'; \
        echo 'post_max_size = 16M'; \
        echo 'upload_max_filesize = 16M'; \
        echo 'max_execution_time = 60'; \
        echo 'memory_limit = 128M'; \
    } > /usr/local/etc/php/conf.d/lms.ini

# Set working directory
WORKDIR /var/www/html

# Copy all project files (docker/mariadb/init.sql IS included — see .dockerignore)
COPY . /var/www/html/

# Create writable storage directories
RUN mkdir -p /var/www/html/src/php/storage/logs \
    && chmod -R 777 /var/www/html/src/php/storage

# Expose default port
EXPOSE 8080

# Start PHP built-in server.
# Railway injects $PORT at runtime. router.php handles all routing.
CMD ["sh", "-c", "exec php -S 0.0.0.0:${PORT:-8080} /var/www/html/router.php"]
