#!/bin/bash

# Start MariaDB service inside container
service mariadb start

# Wait for MariaDB to initialize
sleep 2

# Create food_api database and import schema.sql with real recipes data
mysql -u root -e "CREATE DATABASE IF NOT EXISTS food_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root food_api < /var/www/html/schema.sql

# Ensure uploads directory permissions
mkdir -p /var/www/html/uploads
chmod -R 777 /var/www/html/uploads

# Start Apache web server in foreground
exec apache2-foreground
