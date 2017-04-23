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
RUN php -r "if (hash_file('SHA384', 'composer-setup.php') === '669656bab3166a7aff8a7506b8cb2d1c292f042046c5a994c43155c0be6190fa0355160742ab2e1c88d40d5be660b410') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"


RUN php composer.phar install

# start it
CMD [ "php", "./start.php" ]