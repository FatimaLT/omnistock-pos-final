FROM php:8.1-apache

# Habilitar mod_rewrite para el funcionamiento correcto de las rutas
RUN a2enmod rewrite

# Copiar el contenido de la carpeta omnistock-pos a la raíz web del servidor
COPY omnistock-pos/ /var/www/html/
