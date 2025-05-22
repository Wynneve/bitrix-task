FROM php:8.3-apache

# 1) устанавливаем зависимости
RUN apt-get update \
    && apt-get install -y \
        libzip-dev libpng-dev libjpeg-dev libxml2-dev \
        libfreetype6-dev libpq-dev libonig-dev \
        libcurl4-openssl-dev libssl-dev \
        git unzip curl \
        cron \
    && docker-php-ext-install \
        zip gd xml \
        mysqli pdo_mysql \
        mbstring bcmath \
        curl \
    && a2enmod rewrite

# 2) копируем конфиги
COPY config/php/apache2/php.ini /etc/php/8.3/apache2/php.ini
COPY config/php/apache2/conf.d/10-opcache.ini /etc/php/8.3/apache2/conf.d/10-opcache.ini
COPY config/apache2/apache2.conf /etc/apache2/apache2.conf
COPY config/mysql/mariadb.conf.d/50-server.cnf /etc/mysql/mariadb.conf.d/50-server.cnf

# 3) создаём папку сайта
WORKDIR /var/www/html
COPY web/ /var/www/html/

# 4) даём доступ к 80 порту
EXPOSE 80
