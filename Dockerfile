FROM php:7.4-fpm

# php extensions
RUN docker-php-ext-install pdo pdo_mysql
