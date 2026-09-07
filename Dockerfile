FROM php:8.1-apache

# Install MariaDB server & required PHP extensions
RUN apt-get update && apt-get install -y \
    mariadb-server \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy all project files into Apache web root
COPY . /var/www/html/

# Copy entrypoint startup script
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port 80 for Render.com
EXPOSE 80

# Execute entrypoint script on startup
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
