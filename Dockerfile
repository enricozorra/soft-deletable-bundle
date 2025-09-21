FROM php:8.2-cli

ARG COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_HOME=/composer

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zlib1g-dev \
    libonig-dev \
  && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) gd intl pdo pdo_mysql xml zip mbstring

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Copy composer files first to leverage Docker cache
COPY composer.json composer.lock* /app/

# Install PHP dependencies (including dev deps for running tests)
RUN composer install --no-interaction --prefer-dist --no-progress

# Copy the rest of the project
COPY . /app

# Ensure vendor binaries are available on PATH
ENV PATH="/app/vendor/bin:${PATH}"

# Default to an interactive shell; run tests with: docker run --rm <image> composer phpunit
CMD ["bash"]
