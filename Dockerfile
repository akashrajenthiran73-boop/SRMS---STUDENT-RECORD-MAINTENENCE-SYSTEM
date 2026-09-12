FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and required PHP extensions (GD, Zip, cURL, PDO)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache modules (mod_rewrite, mod_headers)
RUN a2enmod rewrite headers

# Allow .htaccess overrides in Apache
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy project files to Apache web root
COPY . /var/www/html/

# Copy and setup entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN tr -d '\r' < /usr/local/bin/docker-entrypoint.sh > /usr/local/bin/docker-entrypoint-clean.sh \
    && mv /usr/local/bin/docker-entrypoint-clean.sh /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

# Pre-create writable directories
RUN mkdir -p /var/www/html/uploads \
             /var/www/html/uploads/students \
             /var/www/html/student/uploads \
             /var/www/html/faculty/uploads \
             /var/www/html/admin/uploads \
             /var/www/html/data \
    && chmod -R 777 /var/www/html/uploads \
                   /var/www/html/uploads/students \
                   /var/www/html/student/uploads \
                   /var/www/html/faculty/uploads \
                   /var/www/html/admin/uploads \
                   /var/www/html/data \
    && chown -R www-data:www-data /var/www/html

# Expose common web ports (Render dynamically assigns $PORT)
EXPOSE 80 10000

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]