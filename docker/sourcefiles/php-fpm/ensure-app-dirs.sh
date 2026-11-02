#!/bin/sh
# The repo is bind-mounted over /application, so this entrypoint prepares
# writable directories and hands over to the official php-fpm entrypoint.

set -e

for dir in \
    /application/var \
    /application/var/downloads \
    /application/public/uploads/avatars; do
    mkdir -p "${dir}"
    chown -R www-data:www-data "${dir}"
done

exec docker-php-entrypoint "$@"