FROM php:8.2-apache

# Install the docker-php-extension-installer
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

# Install PHP extensions
RUN install-php-extensions gd pdo_mysql mbstring zip

# Fix MPM conflict AFTER extensions are installed
RUN a2dismod mpm_worker 2>/dev/null; \
    a2dismod mpm_event 2>/dev/null; \
    a2enmod mpm_prefork; \
    a2enmod rewrite

# Install WeasyPrint + Arabic fonts + dependencies (Debian Trixie compatible)
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    python3 \
    python3-pip \
    python3-cffi \
    python3-brotli \
    libpango-1.0-0 \
    libpangoft2-1.0-0 \
    libpangocairo-1.0-0 \
    libcairo2 \
    libgdk-pixbuf-2.0-0 \
    libffi-dev \
    shared-mime-info \
    fonts-noto-core \
    fonts-noto-extra \
    fonts-liberation \
    fonts-dejavu-core \
    fonts-freefont-ttf \
    && pip3 install --no-cache-dir --break-system-packages weasyprint \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Verify WeasyPrint works and find real paths for python3/weasyprint
# Remove any existing symlinks first to avoid loops, then create fresh ones
RUN weasyprint --version && \
    rm -f /usr/bin/python3 2>/dev/null; \
    REAL_PYTHON=$(readlink -f $(which python3.11 || which python3.12 || which python3)) && \
    ln -sf "$REAL_PYTHON" /usr/bin/python3 && \
    REAL_WEASY=$(readlink -f $(which weasyprint)) && \
    ln -sf "$REAL_WEASY" /usr/bin/weasyprint && \
    echo "python3 -> $REAL_PYTHON" && echo "weasyprint -> $REAL_WEASY"

# Ensure PATH includes pip install location for Apache subprocess
ENV PATH="/usr/local/bin:/usr/bin:/bin:${PATH}"

# Install Google Fonts (Inter, Noto Sans Arabic) for exact template match
RUN mkdir -p /usr/share/fonts/google && \
    apt-get update && apt-get install -y --no-install-recommends wget && \
    wget -q -O /tmp/inter.zip "https://fonts.google.com/download?family=Inter" && \
    unzip -q /tmp/inter.zip -d /usr/share/fonts/google/inter/ 2>/dev/null || true && \
    wget -q -O /tmp/noto-ar.zip "https://fonts.google.com/download?family=Noto+Sans+Arabic" && \
    unzip -q /tmp/noto-ar.zip -d /usr/share/fonts/google/noto-arabic/ 2>/dev/null || true && \
    rm -f /tmp/inter.zip /tmp/noto-ar.zip && \
    fc-cache -f -v && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy all project files
COPY . .

# Install PHP dependencies in both root and sickleave directory
RUN if [ -f /var/www/html/composer.json ]; then \
        cd /var/www/html && composer install --no-dev --optimize-autoloader --no-interaction; \
    fi && \
    if [ -f /var/www/html/sickleave/composer.json ]; then \
        cd /var/www/html/sickleave && composer install --no-dev --optimize-autoloader --no-interaction; \
    fi

# Create temp directory for PDF generation
RUN mkdir -p /tmp/weasyprint && chmod 777 /tmp/weasyprint

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# PHP configuration
RUN printf "upload_max_filesize = 20M\npost_max_size = 25M\nmemory_limit = 512M\nmax_execution_time = 120\n" > /usr/local/etc/php/conf.d/custom.ini

# Create startup script
RUN printf '#!/bin/bash\nset -e\nexport PATH="/usr/local/bin:/usr/bin:/bin:$PATH"\nLISTEN_PORT="${PORT:-8080}"\na2dismod mpm_event 2>/dev/null || true\na2dismod mpm_worker 2>/dev/null || true\na2enmod mpm_prefork 2>/dev/null || true\necho "Listen ${LISTEN_PORT}" > /etc/apache2/ports.conf\nsed -i "s/<VirtualHost \\*:[0-9]*>/<VirtualHost *:${LISTEN_PORT}>/" /etc/apache2/sites-available/000-default.conf\necho "Starting Apache on port ${LISTEN_PORT}"\nexec apache2-foreground\n' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
