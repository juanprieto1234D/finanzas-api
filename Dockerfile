FROM php:8.2-cli

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

COPY . /app/

WORKDIR /app

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080"]
