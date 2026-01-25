help:
	@echo "+------------------------------------------------------------------------------+"
	@echo "|                         List of available commands:                          |"
	@echo "+------------------------------------------------------------------------------+"
	@echo "1. init ........................ Initialize new application with empty database."
	@echo "2. restart ......................... Restart application with existing database."
	@echo "3. stop ............................ Stop application, make down all containers."
	@echo "4. supervisor-start ..................... Start supervisor for queue processing."
	@echo "5. supervisor-stop ....................... Stop supervisor for queue processing."
	@echo "6. supervisor-restart ................. Restart supervisor for queue processing."
	@echo "7. docker-compose-up ............................. Up docker compose containers."
	@echo "8. docker-compose-down ......................... Down docker compose containers."
	@echo "9. composer-install ............................. Install composer dependencies."
	@echo "10. composer-update .............................. Update composer dependencies."
	@echo "11. db-setup ... Setup database (drop existing, create new, migrate migrations)."
	@echo "12. db-purge ........................................ Delete database directory."
	@echo "13. cs-check ................ Check project by php-cs-fixer without any changes."
	@echo "14. cs-fix ........................................ Fix project by php-cs-fixer."
	@echo "15. test ................................................ Execute PhpUnit tests."
	@echo "16. phpstan ...................... Check project by phpstan without any changes."
	@echo "17. docker-php ....................... Enter to bash shell of php-fpm container."
	@echo "18. docker-pgsql ....................... Enter to bash shell of pgsql container."
	@echo "19. cache-clear ........................................... Clear symfony cache."
	@echo "20. cache-purge ........................................ Delete cache directory."
	@echo "21. lint .......... Fix project by php-cs-fixer and after that check by phpstan."
	@echo "22. yt-dlp-update ....................................... Update yt-dlp package."
	@echo "23. bash .......................................... Alias for docker-php command"
	@echo "24. rm-tmp ................. Clear system directory /tmp inside docker container"
	@echo "25. rm-tmp-chromium . Clear directory /tmp/chromium_data inside docker container"
	@echo "26. peck ......................................... Grammar check by peck linter."
	@echo "+------------------------------------------------------------------------------+"

init: db-purge docker-compose-up composer-install composer-update db-setup supervisor-start cache-clear

restart: docker-compose-down docker-compose-up supervisor-start cache-clear cache-purge

stop: docker-compose-down

supervisor-start:
	docker exec ytdownloader-php-fpm /etc/init.d/supervisor start

supervisor-stop:
	docker exec ytdownloader-php-fpm /etc/init.d/supervisor stop

supervisor-restart:
	docker exec ytdownloader-php-fpm /etc/init.d/supervisor restart

docker-compose-up:
	docker compose -f docker/docker-compose.yml up -d

docker-compose-down:
	docker compose -f docker/docker-compose.yml down

composer-install:
	docker exec ytdownloader-php-fpm composer install

composer-update:
	docker exec ytdownloader-php-fpm composer update

db-setup:
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
	docker exec ytdownloader-php-fpm php bin/phpunit

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
