FROM php:8.2-apache

# 1. Instalar la extensión mysqli para conectar con la base de datos
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# 2. Configurar Apache para que use el puerto que Railway le asigne automáticamente
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

# 3. Copiar tus archivos al servidor
COPY . /var/www/html/

# 4. Dar permisos para evitar errores de lectura
RUN chown -r www-data:www-data /var/www/html
