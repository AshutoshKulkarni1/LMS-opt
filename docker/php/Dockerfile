# ============================================================
# LMS / Test Platform - PHP 8.2 + Apache Production Dockerfile
# ============================================================
FROM php:8.2-apache

# Install required system packages and PHP extensions
# NOTE: 'curl' is NOT listed in docker-php-ext-install - it is not a
# compilable PHP source extension. The curl PHP extension is already
# bundled and enabled in the php:8.2-apache base image.
RUN apt-get update && apt-get install -y --no-install-recommends \
    libonig-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Verify pdo_mysql is loaded in this runtime image
RUN php -m | grep -q pdo_mysql && php --ri pdo_mysql

# Fix MPM conflict: php:8.2-apache non-thread-safe (NTS) requires mpm_prefork.
# Disable mpm_event and mpm_worker (if present) then enable mpm_prefork.
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork

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

# Validate Apache config at build time
RUN apache2ctl -t

# Set application working directory
WORKDIR /var/www/html

# Copy project files into container
COPY . /var/www/html/

# Copy entrypoint script and ensure Unix line endings
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

# Create and configure writable storage directories with www-data ownership
RUN mkdir -p /var/www/html/src/php/storage/logs \
    && chown -R www-data:www-data /var/www/html/src/php/storage \
    && chmod -R 775 /var/www/html/src/php/storage

# Expose web port
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
