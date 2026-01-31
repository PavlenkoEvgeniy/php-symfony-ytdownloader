#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_LOCAL="${ROOT_DIR}/.env.local"
ENV_TEST_LOCAL="${ROOT_DIR}/.env.test.local"
DOCKER_ENV="${ROOT_DIR}/docker/.env"
DOCKER_ENV_EXAMPLE="${ROOT_DIR}/docker/.env.example"

log() {
    echo "[env:init] $*"
}

generate_secret() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 32
    else
        head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n'
    fi
}

encode_rabbit_vhost() {
    local vhost="${1:-/}"

    if [ "${vhost}" = "/" ]; then
        echo "%2f"
    else
        echo "${vhost//\//%2f}"
    fi
}

ensure_docker_env() {
    if [ ! -f "${DOCKER_ENV_EXAMPLE}" ]; then
        log "Missing docker/.env.example; cannot generate docker/.env automatically."
        exit 1
    fi

    if [ ! -f "${DOCKER_ENV}" ]; then
        cp "${DOCKER_ENV_EXAMPLE}" "${DOCKER_ENV}"
        log "Created docker/.env from docker/.env.example"
    else
        log "docker/.env already exists; keeping current values"
    fi
}

load_docker_env() {
    if [ -f "${DOCKER_ENV}" ]; then
        set -a
        # shellcheck disable=SC1090
        source "${DOCKER_ENV}"
        set +a
    fi
}

create_env_local() {
    if [ -f "${ENV_LOCAL}" ]; then
        if ! grep -q '^JWT_PASSPHRASE=' "${ENV_LOCAL}"; then
            echo "JWT_PASSPHRASE=${JWT_PASSPHRASE_VALUE}" >> "${ENV_LOCAL}"
            log "Added JWT_PASSPHRASE to .env.local"
        else
            log "JWT_PASSPHRASE already set in .env.local"
        fi
        log ".env.local already exists; skipping generation"
        return
    fi

    cat > "${ENV_LOCAL}" <<EOF
APP_ENV=dev
APP_SECRET=${APP_SECRET_VALUE}
DATABASE_URL="postgresql://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST}:${DB_INTERNAL_PORT}/${DB_DATABASE}?serverVersion=16&charset=utf8"
REDIS="redis://:${REDIS_PASSWORD}@${REDIS_HOST}:${REDIS_INTERNAL_PORT}"
RABBITMQ_DSN="amqp://${RABBITMQ_USER}:${RABBITMQ_PASSWORD}@${RABBITMQ_HOST}:${RABBITMQ_INTERNAL_PORT}/${RABBITMQ_VHOST_ENCODED}"
TELEGRAM_BOT_ENABLED=false
TELEGRAM_BOT_TOKEN=change_me_please
TELEGRAM_HOST_URL=https://change_me_please.tld
APP_DOWNLOADS_DIR="%kernel.project_dir%/var/downloads"
JWT_PASSPHRASE=${JWT_PASSPHRASE_VALUE}
EOF

    log "Created .env.local"
}

create_env_test_local() {
    if [ -f "${ENV_TEST_LOCAL}" ]; then
        log ".env.test.local already exists; skipping generation"
        return
    fi

    cat > "${ENV_TEST_LOCAL}" <<EOF
KERNEL_CLASS='App\\Kernel'
APP_ENV=test
APP_SECRET=${APP_SECRET_TEST_VALUE}
SYMFONY_DEPRECATIONS_HELPER=999999
PANTHER_APP_ENV=panther
PANTHER_ERROR_SCREENSHOT_DIR=./var/error-screenshots
DATABASE_URL="postgresql://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST}:${DB_INTERNAL_PORT}/${DB_DATABASE}?serverVersion=16&charset=utf8"
REDIS="redis://:${REDIS_PASSWORD}@${REDIS_HOST}:${REDIS_INTERNAL_PORT}"
RABBITMQ_DSN="amqp://${RABBITMQ_USER}:${RABBITMQ_PASSWORD}@${RABBITMQ_HOST}:${RABBITMQ_INTERNAL_PORT}/${RABBITMQ_VHOST_ENCODED}"
EOF

    log "Created .env.test.local"
}

main() {
    ensure_docker_env
    load_docker_env

    DB_HOST="${DB_HOST:-pgsql}"
    DB_DATABASE="${DB_DATABASE:-ytdownloader}"
    DB_USERNAME="${DB_USERNAME:-user}"
    DB_PASSWORD="${DB_PASSWORD:-123456}"
    DB_INTERNAL_PORT=5432

    REDIS_HOST="${REDIS_HOST:-redis}"
    REDIS_PASSWORD="${REDIS_PASSWORD:-123456}"
    REDIS_INTERNAL_PORT=6379

    RABBITMQ_HOST="${RABBITMQ_HOST:-rabbitmq}"
    RABBITMQ_USER="${RABBITMQ_USER:-guest}"
    RABBITMQ_PASSWORD="${RABBITMQ_PASSWORD:-guest}"
    RABBITMQ_VHOST="${RABBITMQ_VHOST:-/}"
    RABBITMQ_VHOST_ENCODED="$(encode_rabbit_vhost "${RABBITMQ_VHOST}")"
    RABBITMQ_INTERNAL_PORT=5672

    APP_SECRET_VALUE="${APP_SECRET:-$(generate_secret)}"
    APP_SECRET_TEST_VALUE="${APP_SECRET_TEST:-$(generate_secret)}"
    JWT_PASSPHRASE_VALUE="${JWT_PASSPHRASE:-$(generate_secret)}"

    create_env_local
    create_env_test_local

    log "Environment files are ready."
}

main "$@"
