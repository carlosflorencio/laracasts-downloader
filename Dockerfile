FROM php:7.0-cli

# install dependences
RUN apt-get update && apt-get install -y libcurl4-gnutls-dev zlib1g-dev git
RUN docker-php-ext-configure curl --with-curl
RUN docker-php-ext-install -j$(nproc) curl zip

# setup the folder for container
COPY . /usr/src/laracastdl
WORKDIR /usr/src/laracastdl

# install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('SHA384', 'composer-setup.php') === 'e115a8dc7871f15d853148a7fbac7da27d6c0030b848d9b3dc09e2a0388afed865e6a3d6b3c0fad45c48e2b5fc1196ae') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"


RUN php composer.phar install

# start it
CMD [ "php", "./start.php" ]