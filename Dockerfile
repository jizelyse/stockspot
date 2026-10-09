FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
 && a2enmod headers rewrite remoteip \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php-extra.ini $PHP_INI_DIR/conf.d/zz-stockspot.ini
COPY docker/apache.conf /etc/apache2/conf-available/stockspot.conf
RUN a2enconf stockspot
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
WORKDIR /var/www/html
COPY includes ./includes
COPY public ./public
RUN chown -R www-data:www-data /var/www/html
HEALTHCHECK --interval=30s --timeout=5s CMD curl -fs http://localhost/index.php >/dev/null || exit 1
