#!/bin/sh
# The whole repository is bind-mounted over /application at container start,
# so permissions cannot be set in the Dockerfile: anything the image creates
# before mount time is shadowed by the mount. This entrypoint makes sure every
# application-writable directory exists and is owned by www-data, then hands
# over to the official php-fpm entrypoint.

set -e

for dir in \
    /application/var \
    /application/var/downloads \
    /application/public/uploads/avatars; do
    mkdir -p "${dir}"
    chown -R www-data:www-data "${dir}"
done

exec docker-php-entrypoint "$@"