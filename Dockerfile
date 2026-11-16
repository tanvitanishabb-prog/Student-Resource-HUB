FROM php:8.2-apache

Install PHP database extensions

RUN docker-php-ext-install mysqli pdo pdo_mysql

Remove all enabled Apache MPM configurations

RUN rm -f /etc/apache2/mods-enabled/mpm_.load 
/etc/apache2/mods-enabled/mpm_.conf 
&& a2enmod mpm_prefork rewrite

Copy project files

COPY . /var/www/html/

Set permissions

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD [“apache2-foreground”]
