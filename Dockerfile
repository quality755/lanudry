# Spin City Laundry — PHP 8.4 + Apache
FROM php:8.4-apache

# --- PHP extensions ------------------------------------------------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-enable pdo_mysql

# --- Apache --------------------------------------------------------------
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '%s\n' \
        '<Directory ${APACHE_DOCUMENT_ROOT}>' \
        '    Options -Indexes +FollowSymLinks' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/docroot.conf \
    && a2enconf docroot

# --- Application ---------------------------------------------------------
WORKDIR /var/www/html

COPY . /var/www/html

# Static assets sit next to the app code; expose them under the web root.
RUN rm -rf /var/www/html/public/assets \
    && cp -r /var/www/html/assets /var/www/html/public/assets \
    && mkdir -p /var/www/html/storage \
    && rm -f /var/www/html/storage/*.sqlite* \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/public/assets \
    && chmod -R 0775 /var/www/html/storage /var/www/html/public/assets

# Serve everything through the front controller, keep real files as-is.
RUN printf '%s\n' \
    'DirectoryIndex index.php' \
    '<IfModule mod_rewrite.c>' \
    '    RewriteEngine On' \
    '    RewriteCond %{REQUEST_FILENAME} -f [OR]' \
    '    RewriteCond %{REQUEST_FILENAME} -d' \
    '    RewriteRule ^ - [L]' \
    '    RewriteRule ^ index.php [L]' \
    '</IfModule>' \
    > /var/www/html/public/.htaccess

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS http://127.0.0.1/index.php?r=login > /dev/null || exit 1
