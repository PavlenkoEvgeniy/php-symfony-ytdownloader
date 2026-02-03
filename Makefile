help:
	@echo "+------------------------------------------------------------------------------+"
	@echo "|                         List of available commands:                          |"
	@echo "+------------------------------------------------------------------------------+"
	@echo "1. env-setup ................................. Generate local environment files."
	@echo "2. init ........................ Initialize new application with empty database."
	@echo "3. ci-cd-init ............................ Initialize new application for CI/CD."
	@echo "4. restart ......................... Restart application with existing database."
	@echo "5. stop ............................ Stop application, make down all containers."
	@echo "6. supervisor-start ..................... Start supervisor for queue processing."
	@echo "7. supervisor-stop ....................... Stop supervisor for queue processing."
	@echo "8. supervisor-restart ................. Restart supervisor for queue processing."
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
	@echo "20. docker-php ....................... Enter to bash shell of php-fpm container."
	@echo "21. docker-pgsql ....................... Enter to bash shell of pgsql container."
	@echo "22. cache-clear ........................................... Clear symfony cache."
	@echo "23. cache-warmup ........................................ Warm up symfony cache."
	@echo "24. cache-purge ........................................ Delete cache directory."
	@echo "25. lint .......... Fix project by php-cs-fixer and after that check by phpstan."
	@echo "26. yt-dlp-update ....................................... Update yt-dlp package."
	@echo "27. bash ......................................... Alias for docker-php command."
	@echo "28. rm-tmp ....................... Clear directory /tmp inside docker container."
	@echo "29. rm-tmp-chromium ................................. Clear chromium temp files."
	@echo "30. peck ......................................... Grammar check by peck linter."
	@echo "31. generate-jwt-keypair ................................ Generate JWT key pair."
	@echo "32. telegram-bot-hook ................................ Add Telegram bot webhook."
	@echo "33. telegram-bot-unhook ........................... Remove Telegram bot webhook."
	@echo "+------------------------------------------------------------------------------+"

env-setup:
	@bash bin/generate-env.sh

init: env-setup db-purge docker-compose-up composer-install generate-jwt-keypair db-setup supervisor-start cache-clear

ci-cd-init: composer-install generate-jwt-keypair db-setup supervisor-start cache-clear

restart: docker-compose-down docker-compose-up supervisor-start cache-clear cache-purge

stop: docker-compose-down

supervisor-start:
	docker exec ytdownloader-php-fpm /etc/init.d/supervisor start

supervisor-stop:
	docker exec ytdownloader-php-fpm /etc/init.d/supervisor stop

supervisor-restart:
	docker exec ytdownloader-php-fpm /etc/init.d/supervisor restart

DOCKER_COMPOSE_FILES ?= docker/docker-compose.yml
DOCKER_COMPOSE_UP_ARGS ?=
DOCKER_COMPOSE_DOWN_ARGS ?=

docker-compose-up:
	docker compose $(foreach file,$(DOCKER_COMPOSE_FILES),-f $(file)) up -d $(DOCKER_COMPOSE_UP_ARGS)

docker-compose-down:
	docker compose $(foreach file,$(DOCKER_COMPOSE_FILES),-f $(file)) down $(DOCKER_COMPOSE_DOWN_ARGS)

composer-install:
	docker exec ytdownloader-php-fpm composer install

composer-update:
	docker exec ytdownloader-php-fpm composer update

db-setup:
	docker exec ytdownloader-pgsql sh -c 'echo "Waiting for Postgres..."; for i in $$(seq 1 30); do pg_isready -U "$$POSTGRES_USER" >/dev/null 2>&1 && exit 0; sleep 1; done; pg_isready -U "$$POSTGRES_USER"'
	docker exec ytdownloader-php-fpm php bin/console doctrine:database:create --if-not-exists
	docker exec ytdownloader-php-fpm php bin/console doctrine:migrations:migrate

db-purge:
	rm -rf ./database/*

cs-check:
	docker exec ytdownloader-php-fpm vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes

cs-fix:
	docker exec ytdownloader-php-fpm vendor/bin/php-cs-fixer fix --allow-risky=yes

test:
	docker exec ytdownloader-php-fpm php bin/console doctrine:database:drop --if-exists --force --env=test
	docker exec ytdownloader-php-fpm php bin/console doctrine:database:create --env=test
	docker exec ytdownloader-php-fpm php bin/console doctrine:migrations:migrate --no-interaction --env=test
	docker exec ytdownloader-php-fpm php bin/console doctrine:fixtures:load --no-interaction --group=all --env=test
	docker exec ytdownloader-php-fpm php bin/phpunit --colors=always

test-coverage:
	docker exec ytdownloader-php-fpm php bin/console doctrine:database:drop --if-exists --force --env=test
	docker exec ytdownloader-php-fpm php bin/console doctrine:database:create --env=test
	docker exec ytdownloader-php-fpm php bin/console doctrine:migrations:migrate --no-interaction --env=test
	docker exec ytdownloader-php-fpm php bin/console doctrine:fixtures:load --no-interaction --group=all --env=test
	docker exec ytdownloader-php-fpm php bin/phpunit --colors=always --coverage-text

phpstan:
	docker exec ytdownloader-php-fpm vendor/bin/phpstan analyse --memory-limit=256M

docker-php:
	docker exec -it ytdownloader-php-fpm bash

docker-pgsql:
	docker exec -it ytdownloader-pgsql bash

cache-clear:
	docker exec ytdownloader-php-fpm php bin/console cache:clear

cache-purge:
	rm -rf ./var/cache/

cache-warmup:
	docker exec ytdownloader-php-fpm php bin/console cache:warmup

lint: cs-fix phpstan peck

yt-dlp-update:
	docker exec ytdownloader-php-fpm pip install --upgrade yt-dlp --break-system-packages

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
