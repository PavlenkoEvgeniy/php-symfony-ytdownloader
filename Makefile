help:
	@echo "+------------------------------------------------------------------------------+"
	@echo "|                         List of available commands:                          |"
	@echo "+------------------------------------------------------------------------------+"
	@echo "1. env-setup ................................. Generate local environment files."
	@echo "2. init ........................ Initialize new application with empty database."
	@echo "3. ci-cd-init ............................ Initialize new application for CI/CD."
	@echo "4. build ...................................... Build docker compose containers."
	@echo "5. restart ......................... Restart application with existing database."
	@echo "6. stop ............................ Stop application, make down all containers."
	@echo "7. supervisor-status .......................... Show supervisor programs status."
	@echo "8. supervisor-restart .............. Reread worker config on the live container."
	@echo "9. docker-compose-up ............................. Up docker compose containers."
	@echo "10. docker-compose-down ........................ Down docker compose containers."
	@echo "11. composer-install ............................ Install composer dependencies."
	@echo "12. composer-update .............................. Update composer dependencies."
	@echo "13. db-setup ... Setup database (drop existing, create new, migrate migrations)."
	@echo "14. db-purge ........................................ Delete database directory."
	@echo "15. cs-check ................ Check project by php-cs-fixer without any changes."
	@echo "16. cs-fix ........................................ Fix project by php-cs-fixer."
	@echo "17. test ................................................ Execute PhpUnit tests."
	@echo "18. test-coverage ......................... Execute PhpUnit tests with coverage."
	@echo "19. phpstan ...................... Check project by phpstan without any changes."
	@echo "20. psalm .......................... Check project by psalm without any changes."
	@echo "21. docker-php ....................... Enter to bash shell of php-fpm container."
	@echo "22. docker-pgsql ....................... Enter to bash shell of pgsql container."
	@echo "23. cache-clear ........................................... Clear symfony cache."
	@echo "24. cache-warmup ........................................ Warm up symfony cache."
	@echo "25. cache-purge ........................................ Delete cache directory."
	@echo "26. lint .......... Fix project by php-cs-fixer and after that check by phpstan."
	@echo "27. yt-dlp-update ....................................... Update yt-dlp package."
	@echo "28. bash ......................................... Alias for docker-php command."
	@echo "29. rm-tmp ....................... Clear directory /tmp inside docker container."
	@echo "30. rm-tmp-chromium ................................. Clear chromium temp files."
	@echo "31. peck ......................................... Grammar check by peck linter."
	@echo "32. generate-jwt-keypair ................................ Generate JWT key pair."
	@echo "33. telegram-bot-hook ................................ Add Telegram bot webhook."
	@echo "34. telegram-bot-unhook ........................... Remove Telegram bot webhook."
	@echo "35. security-check .......... Check Composer packages for known security issues."
	@echo "36. pull ............................ Pull prebuilt php-fpm image (prod deploy)."
	@echo "37. docker-prune ......... Remove unused images, containers and build cache."
	@echo "38. openspec ........................... Run the OpenSpec CLI (ARGS=...)."
	@echo "39. openspec-validate ............... Validate all OpenSpec specs and changes."
	@echo "40. log-purge ...................................... Delete stale var/log files."
	@echo "+------------------------------------------------------------------------------+"

env-setup:
	@bash bin/generate-env.sh

init: env-setup db-purge docker-compose-up composer-install generate-jwt-keypair db-setup cache-clear

ci-cd-init: composer-install generate-jwt-keypair db-setup cache-clear

build:
	docker compose -f docker/docker-compose.yml up -d --build

# Pulls the prebuilt image for production (ADR-0005); PHP_FPM_IMAGE_TAG is required.
pull:
	docker compose $(foreach file,$(DOCKER_COMPOSE_FILES),-f $(file)) pull php-fpm

# Workers start with the container (ADR-0003); stop them around cache:clear,
# because a killed worker never redelivers its message.
restart: docker-compose-down docker-compose-up worker-stop cache-clear worker-start

stop: docker-compose-down

supervisor-status:
	docker exec ytdownloader-php-fpm supervisorctl status

supervisor-restart:
	docker exec ytdownloader-php-fpm supervisorctl reread
	docker exec ytdownloader-php-fpm supervisorctl update

# Waits for supervisord, then stops workers so cache:clear cannot kill them.
worker-stop:
	docker exec ytdownloader-php-fpm sh -c 'for i in $$(seq 1 30); do supervisorctl stop messenger-consume:* >/dev/null 2>&1 && exit 0; sleep 1; done; supervisorctl status'

worker-start:
	docker exec ytdownloader-php-fpm supervisorctl start messenger-consume:*

DOCKER_COMPOSE_FILES ?= docker/docker-compose.yml
DOCKER_COMPOSE_UP_ARGS ?=
DOCKER_COMPOSE_DOWN_ARGS ?=

# OpenSpec is Node-only; run it in a pinned Node 22 container as the host user
# so generated files are owned by the developer, not root.
OPENSPEC_VERSION ?= 1.16.0
OPENSPEC_RUN = docker run --rm --user "$$(id -u):$$(id -g)" \
	-e HOME=/tmp -e npm_config_cache=/tmp/npm-cache \
	-e OPENSPEC_TELEMETRY=0 -e OPENSPEC_NO_UPDATE_CHECK=1 \
	-v "$$(pwd)":/work -w /work node:22-alpine \
	npx --yes @fission-ai/openspec@$(OPENSPEC_VERSION)

docker-compose-up:
	docker compose $(foreach file,$(DOCKER_COMPOSE_FILES),-f $(file)) up -d $(DOCKER_COMPOSE_UP_ARGS)

docker-compose-down:
	docker compose $(foreach file,$(DOCKER_COMPOSE_FILES),-f $(file)) down $(DOCKER_COMPOSE_DOWN_ARGS)

composer-install:
	docker exec ytdownloader-php-fpm composer install

composer-update:
	docker exec ytdownloader-php-fpm composer update

security-check:
	docker exec ytdownloader-php-fpm composer audit --no-interaction

db-setup:
	docker exec ytdownloader-pgsql sh -c 'echo "Waiting for Postgres..."; for i in $$(seq 1 30); do pg_isready -U "$$POSTGRES_USER" >/dev/null 2>&1 && exit 0; sleep 1; done; pg_isready -U "$$POSTGRES_USER"'
	docker exec ytdownloader-php-fpm php bin/console doctrine:database:create --if-not-exists
	docker exec ytdownloader-php-fpm php bin/console doctrine:migrations:migrate

db-purge:
	rm -rf ./database/*

asset-compile:
	docker exec ytdownloader-php-fpm php bin/console asset-map:compile

cs-check:
	docker exec ytdownloader-php-fpm vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes

cs-fix:
	docker exec ytdownloader-php-fpm vendor/bin/php-cs-fixer fix --allow-risky=yes

# phpunit runs as www-data: DG\BypassFinals (enabled in tests/bootstrap.php)
# makes is_writable() use pure mode-bit checks, so as root it reports the
# www-data-owned var/cache/test as unwritable. HOME=/tmp gives www-data a
# writable home (its /var/www is root-owned).
test:
	docker exec ytdownloader-php-fpm php bin/console doctrine:database:drop --if-exists --force --env=test
	docker exec ytdownloader-php-fpm php bin/console doctrine:database:create --env=test
	docker exec ytdownloader-php-fpm php bin/console doctrine:migrations:migrate --no-interaction --env=test
	docker exec ytdownloader-php-fpm php bin/console doctrine:fixtures:load --no-interaction --group=all --env=test
	docker exec -u www-data -e HOME=/tmp ytdownloader-php-fpm php bin/phpunit --colors=always

test-coverage:
	docker exec ytdownloader-php-fpm php bin/console doctrine:database:drop --if-exists --force --env=test
	docker exec ytdownloader-php-fpm php bin/console doctrine:database:create --env=test
	docker exec ytdownloader-php-fpm php bin/console doctrine:migrations:migrate --no-interaction --env=test
	docker exec ytdownloader-php-fpm php bin/console doctrine:fixtures:load --no-interaction --group=all --env=test
	docker exec -u www-data -e HOME=/tmp ytdownloader-php-fpm php bin/phpunit --colors=always --coverage-text

phpstan:
	docker exec ytdownloader-php-fpm vendor/bin/phpstan analyse --memory-limit=256M

psalm:
	docker exec ytdownloader-php-fpm vendor/bin/psalm --no-cache --config=psalm.xml.dist

docker-php:
	docker exec -it ytdownloader-php-fpm bash

docker-pgsql:
	docker exec -it ytdownloader-pgsql bash

# Rebuild cache as www-data; the root rm drops caches left by root processes.
cache-clear:
	docker exec ytdownloader-php-fpm sh -c 'for i in $$(seq 1 60); do supervisorctl status queue-reset-once 2>/dev/null | grep -Eq "RUNNING|STARTING" || exit 0; sleep 1; done; exit 1'
	docker exec ytdownloader-php-fpm rm -rf var/cache
	docker exec -u www-data -e HOME=/tmp ytdownloader-php-fpm php bin/console cache:clear
	docker exec ytdownloader-php-fpm chown -R www-data:www-data var/cache

# Deletes var/cache; safe only while the stack is stopped (see restart).
cache-purge:
	rm -rf ./var/cache/

log-purge:
	rm -rf ./var/log/*

# Clean the host after a deploy. Named volumes (Redis data) are kept because
# `docker system prune` does not remove volumes unless --volumes is passed.
docker-prune:
	docker system prune --all -f
	docker builder prune --all -f

doctrine-cache-clear:
	docker exec ytdownloader-php-fpm php bin/console cache:pool:clear doctrine.system_cache_pool --env=prod
	docker exec ytdownloader-php-fpm php bin/console cache:pool:clear doctrine.result_cache_pool --env=prod

cache-warmup:
	docker exec -u www-data -e HOME=/tmp ytdownloader-php-fpm php bin/console cache:warmup

lint: cs-fix phpstan psalm peck

yt-dlp-update:
	docker exec ytdownloader-php-fpm python3 -m pip install --upgrade yt-dlp --break-system-packages

bash: docker-php

rm-tmp:
	docker exec ytdownloader-php-fpm rm -rf /tmp/*

rm-tmp-chromium:
	docker exec ytdownloader-php-fpm rm -rf /tmp/chromium_data/*

peck:
	docker exec ytdownloader-php-fpm vendor/bin/peck

generate-jwt-keypair:
	docker exec ytdownloader-php-fpm php bin/console lexik:jwt:generate-keypair --overwrite -n

telegram-bot-hook:
	docker exec ytdownloader-php-fpm php bin/console app:telegram-hook

telegram-bot-unhook:
	docker exec ytdownloader-php-fpm php bin/console app:telegram-unhook

.PHONY: openspec openspec-validate doctrine-cache-clear

openspec:
	$(OPENSPEC_RUN) $(ARGS)

openspec-validate:
	$(OPENSPEC_RUN) validate --all --strict
