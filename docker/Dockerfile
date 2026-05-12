FROM php:7.4-apache

RUN apt-get update \
 && apt-get install -y git zlib1g-dev libzip-dev libxml2-dev \
 && docker-php-ext-install zip soap pdo pdo_mysql \
 && a2enmod rewrite \
 && sed -i 's!/var/www/html!/var/www/public!g' /etc/apache2/sites-available/000-default.conf \
 && mkdir -p /var/www/public \
 && mkdir -p /var/www/public/archivos \
 && mkdir -p /var/www/data/sessiones \
 && chown -R www-data:www-data /var/www/data \
 && chown www-data:www-data /var/www/public/archivos \
 && chmod -R 755 /var/www/data/sessiones \
 && chmod 775 /var/www/public/archivos \
 && curl -sS https://getcomposer.org/installer \
  | php -- --install-dir=/usr/local/bin --filename=composer --1

WORKDIR /var/www
