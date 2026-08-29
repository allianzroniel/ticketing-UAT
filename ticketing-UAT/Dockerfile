FROM php:8.2-apache

# Install database drivers
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy application code
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Fix Apache MPM module conflict
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork

# Configure Apache to use Railway's dynamic PORT variable
RUN sed -i "s/80/\${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Start Apache
CMD ["apache2-foreground"]
