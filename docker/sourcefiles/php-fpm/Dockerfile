FROM composer:2 AS composer
FROM php:8.4-fpm

WORKDIR "/application"

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
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

RUN docker-php-ext-configure pgsql --with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo_pgsql pgsql zip mbstring exif pcntl bcmath gd intl

RUN pecl install redis && docker-php-ext-enable redis

RUN apt-get install -y pip
RUN apt-get install -y ffmpeg
RUN pip install --upgrade yt-dlp --break-system-packages

RUN apt-get update && \
    apt-get install -y -o Dpkg::Options::="--force-confold" supervisor

# Install browser for generating cookies
RUN apt-get install chromium -y
RUN mkdir -p /tmp/chromium_data \
    && chromium --headless --disable-gpu --no-sandbox --user-data-dir=/tmp/chromium_data --no-first-run https://example.com --timeout=3000 2>/dev/null \
    && chown -R www-data:www-data /tmp/chromium_data

# Install aspell for checking grammar in project
RUN apt-get install aspell aspell-en -y \
    && rm -rf /var/lib/apt/lists/*

# The bind mount shadows image content, so writable dirs are prepared at start.
COPY ensure-app-dirs.sh /usr/local/bin/ensure-app-dirs.sh
RUN chmod +x /usr/local/bin/ensure-app-dirs.sh
ENTRYPOINT ["/usr/local/bin/ensure-app-dirs.sh"]
# Supervisord is PID 1 so workers always run with the container (ADR-0003).
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]
