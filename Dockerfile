FROM php:8.2-apache

# يكسر الكاش في Railway (غيّر الرقم عند الحاجة)
ARG CACHE_BUST=5
RUN echo "cache bust: $CACHE_BUST"

# Enable rewrite + ServerName
RUN a2enmod rewrite \
 && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

# اجعل DocumentRoot داخل sickleave
RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/sickleave#' /etc/apache2/sites-available/000-default.conf

# ✅ الحل الثاني: صلاحيات المجلد داخل نفس الـ vhost (الأضمن)
RUN printf '\n<Directory "/var/www/html/sickleave">\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
 >> /etc/apache2/sites-available/000-default.conf

# DirectoryIndex
RUN printf "\n<IfModule dir_module>\n    DirectoryIndex index.htm index.html index.php\n</IfModule>\n" \
    >> /etc/apache2/apache2.conf

# MySQL extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
CMD ["/usr/local/bin/docker-entrypoint.sh"]
