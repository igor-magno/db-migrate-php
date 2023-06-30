FROM php:7.4-fpm

# php extensions
RUN docker-php-ext-install pdo pdo_mysql

# composer install 
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
