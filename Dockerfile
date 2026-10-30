# Spin City Laundry — PHP 8.4 + Apache, serves public/ on port 80
FROM php:8.4-apache

# pdo_mysql for MySQL deployments; pdo_sqlite ships with the base image and
# powers the SQLite fallback used when no DB_HOST is configured.
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

# Production PHP configuration (errors logged to stderr, never displayed).
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Application code
WORKDIR /var/www/html
COPY . /var/www/html

# Serve the front controller as the document root.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT.!}!g" /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
        'DirectoryIndex index.php' \
        '<IfModule mod_rewrite.c>' \
        '    RewriteEngine On' \
        '    RewriteCond %{REQUEST_FILENAME} -f [OR]' \
        '    RewriteCond %{REQUEST_FILENAME} -d' \
        '    RewriteRule ^ - [L]' \
        '    RewriteRule ^ index.php [L]' \
        '</IfModule>' \
        > /var/www/html/public/.htaccess

# Static assets are referenced as assets/... from the web root.
RUN rm -rf /var/www/html/public/assets \
    && cp -r /var/www/html/assets /var/www/html/public/assets \
    && mkdir -p /var/www/html/storage \
    && rm -f /var/www/html/storage/*.sqlite* \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/public \
    && chmod -R 0777 /var/www/html/storage /var/www/html/public/assets

EXPOSE 80
