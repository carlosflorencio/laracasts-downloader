FROM php:7.0-cli

# install dependences
RUN apt-get update && apt-get install -y libcurl4-gnutls-dev zlib1g-dev git
RUN docker-php-ext-configure curl --with-curl
RUN docker-php-ext-install -j$(nproc) curl zip

# setup the folder for container
COPY . /usr/src/laracastdl
WORKDIR /usr/src/laracastdl

# install composer
RUN curl --silent --show-error https://getcomposer.org/installer | php

RUN php composer.phar install

# start it
CMD [ "php", "./start.php" ]