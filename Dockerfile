FROM php:apache

# Instalar la extensión mysqli que falta según tus logs
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copiar todos tus archivos al servidor
COPY . /var/www/html/

# Asegurar que Apache escuche en el puerto que Railway le asigne
CMD ["apache2-foreground"]
