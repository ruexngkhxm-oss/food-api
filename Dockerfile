FROM php:8.1-apache

# Install required PHP extensions for SQLite and Image handling
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libsqlite3-dev \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_sqlite

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy all project files into Apache web root
COPY . /var/www/html/

# Set permissions for database and uploads directory
RUN mkdir -p /var/www/html/uploads && chmod -R 777 /var/www/html/uploads /var/www/html/database.sqlite

# Expose port 80 for Render.com
EXPOSE 80

# Start Apache web server
CMD ["apache2-foreground"]
