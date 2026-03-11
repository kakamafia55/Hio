FROM php:8.2-apache

# Install necessary PHP extensions
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    && docker-php-ext-install dom xml

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy the PHP file to the web root
COPY proxy.php /var/www/html/index.php

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]
