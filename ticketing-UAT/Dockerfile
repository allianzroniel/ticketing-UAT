FROM php:8.2-apache

# Install system dependencies and MySQL drivers
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy local project files into the Apache container web root
COPY . /var/www/html/

# Set permissions so Apache can read/write smoothly
RUN chown -R www-data:www-data /var/www/html
