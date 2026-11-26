FROM php:8.2-apache

# Instalar extensiones de MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Copiar todo el repositorio al contenedor
COPY . /var/www/html/

# Apuntar el DocumentRoot de Apache a la subcarpeta 'omnistock-pos' donde vive tu index.php
RUN sed -i 's!/var/www/html!/var/www/html/omnistock-pos!g' /etc/apache2/sites-available/000-default.conf
RUN sed -i 's!/var/www/!/var/www/html/omnistock-pos!g' /etc/apache2/apache2.conf

# Ajustar permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
