FROM php:8.2-apache

# Install GD library and required dependencies for Captcha images
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Copy project files to Apache web root
COPY . /var/www/html/

EXPOSE 80