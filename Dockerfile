# ============================================================
# LMS / Test Platform - PHP 8.2 CLI Built-in Server
# ============================================================
# Replaces the PHP+Apache image to eliminate recurring MPM
# conflicts on Railway. PHP's built-in server is used as a
# lightweight demo/staging host (not intended for high-traffic
# production use).
#
# Serves: clean URLs, PHP pages, static assets, POST, API
# via router.php (already written for cli-server).
# ============================================================
FROM php:8.2-cli

# Install required system libraries and PHP extensions.
# - pdo_mysql : database access (PDO MySQL/MariaDB driver)
# - mbstring  : multibyte string functions used throughout the app
# - zip       : (optional) ZipArchive if any admin export features need it
# json, gzip/zlib, openssl are built into the cli image.
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

# Verify pdo_mysql is present in the final runtime image
RUN php -m | grep -q pdo_mysql \
    && php --ri pdo_mysql \
    && echo "pdo_mysql OK"

# Set working directory to web root
WORKDIR /var/www/html

# Copy all project files
COPY . /var/www/html/

# Create and configure writable storage directories
RUN mkdir -p /var/www/html/src/php/storage/logs \
    && chown -R www-data:www-data /var/www/html/src/php/storage \
    && chmod -R 775 /var/www/html/src/php/storage

# Expose default port (Railway overrides this via $PORT env var)
EXPOSE 8080

# Start PHP built-in server on all interfaces.
# PORT is injected by Railway at runtime. Defaults to 8080 if not set.
# router.php handles: clean URLs, static assets, API endpoints, security.
CMD ["sh", "-c", "exec php -S 0.0.0.0:${PORT:-8080} router.php"]
