FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql mysqli && a2enmod rewrite
COPY web/ /var/www/html/
WORKDIR /var/www/html
