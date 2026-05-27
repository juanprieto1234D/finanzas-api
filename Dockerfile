FROM php:8.2-apache

# Instalar y habilitar mysqli
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Configurar Apache para que no cargue múltiples MPM
RUN a2dismod mpm_event && a2enmod mpm_prefork

# Copiar los archivos al servidor
COPY . /var/www/html/

# Exponer el puerto 80
EXPOSE 80
