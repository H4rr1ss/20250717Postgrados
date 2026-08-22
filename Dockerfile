FROM php:7.0-apache

COPY --from=debian/eol:stretch /etc/apt/sources.list /etc/apt/sources.list

RUN apt-get update -o Acquire::Check-Valid-Until=false --allow-unauthenticated \
 && apt-get install -y --allow-unauthenticated git zlib1g-dev \
 && docker-php-ext-install zip pdo_mysql \
 && a2enmod rewrite \
 && sed -i 's!/var/www/html!/var/www/public!g' /etc/apache2/sites-available/000-default.conf \
 && mv /var/www/html /var/www/public \
 && curl -sS https://getcomposer.org/installer \
  | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www
