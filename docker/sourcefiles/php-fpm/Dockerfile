FROM php:8.3-fpm

WORKDIR "/application"

RUN apt-get update

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add dependencies
RUN apt-get update && apt-get install -y \
    libsnmp-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl

RUN apt-get update && apt-get install -y \
    curl \
    && curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install --global yarn

# Add extensions
RUN docker-php-ext-configure pgsql --with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo_pgsql pgsql zip mbstring exif pcntl bcmath gd intl

# Install youtube-dl
RUN apt-get install -y pip
RUN apt-get install -y ffmpeg
RUN pip install --upgrade yt-dlp --break-system-packages
RUN apt install -y mc
RUN apt-get install supervisor

# Create download directory
RUN mkdir -p /application/var/downloads/
RUN chmod -R 775 /application/var/downloads/
RUN chown -R www-data:www-data /application/var/downloads/
