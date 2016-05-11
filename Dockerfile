FROM php:7.0-apache
RUN a2enmod rewrite
COPY . /var/www/
COPY public /var/www/html
COPY config.docker.php /var/www/config.php
