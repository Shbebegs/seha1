FROM php:8.2-apache

# Install the docker-php-extension-installer (most reliable way)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

# Install PHP extensions
RUN install-php-extensions gd pdo_mysql mbstring zip

# Fix MPM conflict AFTER extensions are installed
RUN a2dismod mpm_worker 2>/dev/null; \
    a2dismod mpm_event 2>/dev/null; \
    a2enmod mpm_prefork; \
    a2enmod rewrite

# Install system tools needed for Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy all project files first
COPY . .

# Install PHP dependencies (mPDF) in BOTH root and sickleave directory
RUN if [ -f /var/www/html/composer.json ]; then \
        cd /var/www/html && composer install --no-dev --optimize-autoloader --no-interaction; \
    fi && \
    if [ -f /var/www/html/sickleave/composer.json ]; then \
        cd /var/www/html/sickleave && composer install --no-dev --optimize-autoloader --no-interaction; \
    fi

# Create temp directory for mPDF with proper permissions
RUN mkdir -p /tmp/mpdf && chmod 777 /tmp/mpdf

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# PHP configuration
RUN printf "upload_max_filesize = 20M\npost_max_size = 25M\nmemory_limit = 256M\nmax_execution_time = 120\n" > /usr/local/etc/php/conf.d/custom.ini

# Create startup script
RUN printf '#!/bin/bash\nset -e\nLISTEN_PORT="${PORT:-8080}"\na2dismod mpm_event 2>/dev/null || true\na2dismod mpm_worker 2>/dev/null || true\na2enmod mpm_prefork 2>/dev/null || true\necho "Listen ${LISTEN_PORT}" > /etc/apache2/ports.conf\nsed -i "s/<VirtualHost \\*:[0-9]*>/<VirtualHost *:${LISTEN_PORT}>/" /etc/apache2/sites-available/000-default.conf\necho "Starting Apache on port ${LISTEN_PORT}"\nexec apache2-foreground\n' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
