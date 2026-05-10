FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install the docker-php-extension-installer (most reliable way to install PHP extensions)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

# Install PHP extensions using the reliable installer
RUN install-php-extensions gd pdo_mysql mbstring zip

# Install system tools needed for Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (for Docker cache optimization)
COPY composer.json ./

# Install PHP dependencies (mPDF)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy all project files
COPY . .

# Create temp directory for mPDF with proper permissions
RUN mkdir -p /tmp/mpdf && chmod 777 /tmp/mpdf

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache to listen on PORT environment variable (Railway requirement)
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# PHP configuration for larger uploads and mPDF
RUN echo "upload_max_filesize = 20M\npost_max_size = 25M\nmemory_limit = 256M\nmax_execution_time = 120" > /usr/local/etc/php/conf.d/custom.ini

EXPOSE ${PORT}

CMD ["apache2-foreground"]
