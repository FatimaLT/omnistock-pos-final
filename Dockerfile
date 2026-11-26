FROM php:8.2-apache

# Instalar extensiones de MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Copiar todo el código del proyecto al contenedor
COPY . /var/www/html/

# Configurar permisos completos para evitar errores 403 Forbidden
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Ajustar el puerto por defecto de Apache para Render
ENV APACHE_DOCUMENT_ROOT /var/www/html
# (Si tu index.php está dentro de una carpeta public, cambia la línea de arriba por: /var/www/html/public)

EXPOSE 80
