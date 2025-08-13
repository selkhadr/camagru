# Production Dockerfile for Camagru
FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    curl \
    cron \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_mysql \
        mysqli \
        zip \
        intl \
        opcache

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Configure Apache
RUN a2enmod rewrite headers deflate expires ssl
COPY apache-prod.conf /etc/apache2/sites-available/000-default.conf
COPY apache-ssl.conf /etc/apache2/sites-available/default-ssl.conf
RUN a2ensite default-ssl

# PHP configuration for production
COPY php-prod.ini /usr/local/etc/php/php.ini

# Copy application files
COPY public/ /var/www/html/
COPY src/ /var/www/src/

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/images/uploads \
    && mkdir -p /var/www/src/logs \
    && chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/images/uploads

# Copy supervisor configuration
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy cron jobs
COPY crontab /etc/cron.d/camagru-cron
RUN chmod 0644 /etc/cron.d/camagru-cron \
    && crontab /etc/cron.d/camagru-cron

# Health check
COPY health.php /var/www/html/health.php
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health.php || exit 1

# Security: Remove sensitive files and set proper permissions
RUN rm -f /var/www/html/health.php \
    && find /var/www -name "*.log" -delete \
    && find /var/www -type f -exec chmod 644 {} \; \
    && find /var/www -type d -exec chmod 755 {} \;

# Expose ports
EXPOSE 80 443

# Start supervisor (manages Apache and cron)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]