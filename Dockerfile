FROM php:8.2-apache

# Install PHP database extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Fix Apache MPM conflict
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
    /etc/apache2/mods-enabled/mpm_*.conf

# Enable only prefork and rewrite
RUN a2enmod mpm_prefork rewrite

# Verify Apache configuration
RUN apache2ctl -M

# Copy project files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache port
EXPOSE 80

CMD ["apache2-foreground"]