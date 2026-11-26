FROM php:8.1-apache

# Habilitar mod_rewrite para URLs amigables
RUN a2enmod rewrite

# Copiar el contenido de la carpeta omnistock-pos directamente a la raíz web de Apache
COPY omnistock-pos/ /var/www/html/
