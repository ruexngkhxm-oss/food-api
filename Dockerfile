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

# Create uploads directory and set full permissions
RUN mkdir -p /var/www/html/uploads && chmod -R 777 /var/www/html/uploads

# Pre-initialize MariaDB database and import schema.sql with real recipes
RUN service mariadb start && \
    mysql -e "CREATE DATABASE IF NOT EXISTS food_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" && \
    mysql food_api < /var/www/html/schema.sql

# Expose port 80 for Render.com
EXPOSE 80

# Start MariaDB service and Apache web server on container startup
CMD service mariadb start && apache2-foreground
