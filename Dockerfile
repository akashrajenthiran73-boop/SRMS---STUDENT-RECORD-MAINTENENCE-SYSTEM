FROM php:8.2-apache

# Create uploads directory and set permissions
RUN mkdir -p /var/www/html/student/uploads \
    && chmod -R 777 /var/www/html/student/uploads \
    && chown -R www-data:www-data /var/www/html/student/uploads

    
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