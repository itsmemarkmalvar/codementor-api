# Multi-stage build for CodeMentor API with Java support
FROM php:8.2-fpm as base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application code first
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Stage 2: Add Java support
FROM base as java-stage

# Install OpenJDK 17
RUN apt-get update && apt-get install -y \
    default-jdk \
    && rm -rf /var/lib/apt/lists/*

# Set Java environment variables
ENV JAVA_HOME=/usr/lib/jvm/default-java
ENV PATH=$JAVA_HOME/bin:$PATH

# Verify Java installation
RUN java -version && javac -version

# Create temp directory for Java execution
RUN mkdir -p /var/www/html/storage/app/java-execution \
    && chown -R www-data:www-data /var/www/html/storage/app/java-execution

# Final stage
FROM java-stage as production

# Copy environment file
COPY .env.example .env

# Generate application key
RUN php artisan key:generate

# Run migrations (optional - can be done at runtime)
# RUN php artisan migrate --force

# Expose port
EXPOSE 8000

# Start the application
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
