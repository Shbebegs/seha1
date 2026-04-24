FROM php:8.2-apache

# Enable rewrite, allow .htaccess, and set ServerName
RUN a2enmod rewrite \
 && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Set DocumentRoot to /sickleave (make sure this folder exists!)
RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/sickleave#' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's#<Directory /var/www/html/>#<Directory /var/www/html/sickleave/>#' /etc/apache2/apache2.conf

# Add index.htm to DirectoryIndex
RUN printf "\n<IfModule dir_module>\n    DirectoryIndex index.htm index.html index.php\n</IfModule>\n" \
    >> /etc/apache2/apache2.conf

# MySQL extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Entry point for Railway runtime fixes
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
CMD ["docker-entrypoint.sh"]
