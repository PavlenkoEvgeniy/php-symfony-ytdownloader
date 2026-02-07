FROM php:8.4-fpm

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
    libicu-dev \
    zip \
    unzip \
    git \
    curl

# Install Xdebug only when requested
ARG XDEBUG_INSTALL=false
RUN if [ "$XDEBUG_INSTALL" = "true" ]; then \
        pecl install xdebug && docker-php-ext-enable xdebug; \
    fi

RUN apt-get update && apt-get install -y \
    curl \
    && curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install --global yarn

# Add extensions
RUN docker-php-ext-configure pgsql --with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo_pgsql pgsql zip mbstring exif pcntl bcmath gd intl

# Install redis
RUN pecl install redis && docker-php-ext-enable redis

# Install rabbit
RUN apt-get install -y \
    librabbitmq-dev \
    libssh-dev \
    pkg-config \
    && docker-php-ext-install sockets \
    && pecl install amqp \
    && docker-php-ext-enable amqp

# Install youtube-dl
RUN apt-get install -y pip
RUN apt-get install -y ffmpeg
RUN pip install --upgrade yt-dlp --break-system-packages

# Install supervisor
RUN apt-get update && \
    apt-get install -y -o Dpkg::Options::="--force-confold" supervisor

# Install browser for generating cookies
RUN apt-get install chromium -y
RUN mkdir -p /tmp/chromium_data
RUN chromium --headless --disable-gpu --no-sandbox --user-data-dir=/tmp/chromium_data --no-first-run https://example.com --timeout=3000 2>/dev/null

# Install aspell for checking grammar in project
RUN apt-get install aspell aspell-en -y

# Create download directory
RUN mkdir -p /application/var/downloads/
RUN chmod -R 775 /application/var/downloads/
RUN chown -R www-data:www-data /application/var/downloads/
