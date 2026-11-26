FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mbstring pdo_mysql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY omnistock-pos/ /var/www/html/

RUN mkdir -p /var/www/html/public/img/productos /var/www/html/public/img/mensajes \
    && chown -R www-data:www-data /var/www/html/public/img/productos /var/www/html/public/img/mensajes

EXPOSE 80

CMD ["apache2-foreground"]
