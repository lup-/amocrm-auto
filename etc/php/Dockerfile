FROM php:7.3-fpm
RUN apt-get install -y \
        libzip-dev \
        zip \
  && docker-php-ext-configure zip --with-libzip \
  && docker-php-ext-install zip \
  && pecl install mongodb \
  &&  echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongo.ini
