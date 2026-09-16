FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# The project root, not the webroot. Apache mounts the project at this same
# path, because SetHandler proxy:fcgi sends Apache's local SCRIPT_FILENAME here
# and the two containers must agree on what that path means.
WORKDIR /var/www/app

# Change ownership of our applications
RUN mkdir -p /var/www/app && chown -R www-data:www-data /var/www/app
