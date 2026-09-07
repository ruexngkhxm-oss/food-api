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

# Create required MariaDB directories and set permissions for Debian/Docker
RUN mkdir -p /var/run/mysqld /var/lib/mysql && \
    chown -R mysql:mysql /var/run/mysqld /var/lib/mysql && \
    mysql_install_db --user=mysql --datadir=/var/lib/mysql

# Pre-initialize MariaDB database, configure root permissions, and import schema.sql
RUN mysqld_safe --user=mysql --datadir='/var/lib/mysql' & \
    sleep 4 && \
    mysql -u root -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' IDENTIFIED BY '' WITH GRANT OPTION; GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' IDENTIFIED BY '' WITH GRANT OPTION; FLUSH PRIVILEGES;" && \
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS food_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" && \
    mysql -u root food_api < /var/www/html/schema.sql

# Create uploads directory and set full permissions
RUN mkdir -p /var/www/html/uploads && chmod -R 777 /var/www/html/uploads

# Expose port 80 for Render.com
EXPOSE 80

# Start MariaDB service and Apache web server on container startup
CMD ["sh", "-c", "service mariadb start && apache2-foreground"]
