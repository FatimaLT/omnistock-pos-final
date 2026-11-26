FROM php:8.2-apache

# Instalar extensiones necesarias para MySQL y PDO
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar mod_rewrite de Apache por si tu app lo necesita
RUN a2enmod rewrite

# Copiar el código del proyecto al directorio web de Apache
COPY . /var/www/html/

# Ajustar permisos si es necesario
RUN chown -R www-data:www-data /var/www/html
