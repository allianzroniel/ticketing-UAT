FROM php:8.2-apache

# Install database drivers
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy application code
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Configure Apache to listen on Railway's dynamic PORT environment variable
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Start Apache in the foreground
CMD apache2-foreground
