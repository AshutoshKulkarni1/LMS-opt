# ============================================================
# LMS / Test Platform - PHP 8.2 + Apache Production Dockerfile
# ============================================================
FROM php:8.2-apache

# Install required system packages and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    libcurl4-openssl-dev \
    libonig-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Verify required database extensions are enabled in runtime image
RUN php -m | grep -q pdo_mysql \
    && php --ri pdo_mysql

# Enable required Apache modules for routing, security, and performance
RUN a2enmod rewrite headers deflate expires

# Configure Apache virtual host and directory permissions for clean routing
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && { \
        echo '<Directory /var/www/html>'; \
        echo '    Options -Indexes +FollowSymLinks'; \
        echo '    AllowOverride All'; \
        echo '    Require all granted'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/lms.conf \
    && a2enconf lms

# Set application working directory
WORKDIR /var/www/html

# Copy project files into container
COPY . /var/www/html/

# Copy and configure entrypoint script for dynamic cloud port binding ($PORT)
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create and configure writable storage directories with www-data ownership
RUN mkdir -p /var/www/html/src/php/storage/logs \
    && chown -R www-data:www-data /var/www/html/src/php/storage \
    && chmod -R 775 /var/www/html/src/php/storage

# Only the web port is exposed
EXPOSE 80

# Healthcheck to verify Apache is serving requests
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:${PORT:-80}/ || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
