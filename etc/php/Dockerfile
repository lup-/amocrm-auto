FROM php:7.1-fpm
RUN apt-get install -y \
        libzip-dev \
        zip \
  && docker-php-ext-configure zip --with-libzip \
  && docker-php-ext-install zip