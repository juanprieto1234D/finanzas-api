FROM php:8.2-apache

# Fijar conflicto de MPM
RUN a2dismod mpm_event mpm_worker 2>/dev/null; \
    a2enmod mpm_prefork

# Instalar mysqli
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

COPY . /var/www/html/

EXPOSE 80
