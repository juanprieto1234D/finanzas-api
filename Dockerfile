FROM php:8.2-apache

# Instalar extensión mysqli
RUN docker-php-ext-install mysqli

# Cambiar el puerto por defecto de Apache al que Railway espera (8080)
RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/0000-default.conf /etc/apache2/ports.conf

# Copiar archivos al contenedor
COPY . /var/www/html/

# Exponer el puerto
EXPOSE 8080
