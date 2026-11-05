FROM composer:2 AS composer
FROM php:8.4-fpm-alpine

WORKDIR "/application"

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

# Runtime tools: ffmpeg/yt-dlp for media, Chromium for cookies, aspell for the
# grammar check, supervisor as PID 1.
RUN apk add --no-cache \
    ffmpeg \
    chromium \
    aspell \
    aspell-en \
    supervisor \
    git \
    unzip \
    curl \
    py3-pip \
    libpq \
    libzip \
    icu-libs \
    oniguruma \
    libpng \
    libjpeg-turbo \
    freetype \
    libxml2

# Alpine packages provide the same PHP 8.4.27 extensions as the Docker image.
# Copy their modules instead of compiling: this avoids the PECL network and the
# build toolchain, and keeps the image small.
RUN apk add --no-cache \
        php84 \
        php84-pdo_pgsql \
        php84-exif \
        php84-pcntl \
        php84-bcmath \
        php84-gd \
        php84-intl \
        php84-pecl-msgpack \
        php84-pecl-redis \
    && EXTENSION_DIR="$(php -r 'echo ini_get("extension_dir");')" \
    && cp /usr/lib/php84/modules/*.so "${EXTENSION_DIR}/" \
    && docker-php-ext-enable msgpack pdo_pgsql exif pcntl bcmath gd intl redis

# Install Xdebug only when requested.
ARG XDEBUG_INSTALL=false
RUN if [ "$XDEBUG_INSTALL" = "true" ]; then \
        apk add --no-cache php84-pecl-xdebug \
        && EXTENSION_DIR="$(php -r 'echo ini_get("extension_dir");')" \
        && cp /usr/lib/php84/modules/xdebug.so "${EXTENSION_DIR}/" \
        && docker-php-ext-enable xdebug; \
    fi

# YouTube changes often, so keep yt-dlp current through pip; make yt-dlp-update
# uses the same interpreter.
RUN python3 -m pip install --no-cache-dir --upgrade yt-dlp --break-system-packages

# Install browser for generating cookies. Chromium has no --timeout flag; bound
# the profile warm-up with timeout and the virtual time budget instead.
RUN mkdir -p /tmp/chromium_data \
    && (timeout 20s chromium --headless --disable-gpu --no-sandbox --disable-dev-shm-usage --user-data-dir=/tmp/chromium_data --no-first-run --virtual-time-budget=5000 https://example.com 2>/dev/null || true) \
    && chown -R www-data:www-data /tmp/chromium_data

# Alpine's supervisor package includes /etc/supervisor.d/*.ini; the compose stack
# mounts the programs at /etc/supervisor/conf.d, so ship our own config.
COPY supervisord.conf /etc/supervisord.conf

# The bind mount shadows image content, so writable dirs are prepared at start.
COPY ensure-app-dirs.sh /usr/local/bin/ensure-app-dirs.sh
RUN chmod +x /usr/local/bin/ensure-app-dirs.sh
ENTRYPOINT ["/usr/local/bin/ensure-app-dirs.sh"]
# Supervisord is PID 1 so workers always run with the container (ADR-0003).
CMD ["supervisord", "-c", "/etc/supervisord.conf", "-n"]
