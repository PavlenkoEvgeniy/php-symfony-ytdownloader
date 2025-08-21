help:
	@echo "+------------------------------------------------------------------------------+"
	@echo "|                         List of available commands:                          |"
	@echo "+------------------------------------------------------------------------------+"
	@echo "1. init ........................ Initialize new application with empty database."
	@echo "2. restart ......................... Restart application with existing database."
	@echo "3. supervisor-start ..................... Start supervisor for queue processing."
	@echo "4. supervisor-stop ....................... Stop supervisor for queue processing."
	@echo "5. supervisor-restart ................. Restart supervisor for queue processing."
	@echo "6. docker-compose-up ............................. Up docker-compose containers."
	@echo "7. docker-compose-down ......................... Down docker-compose containers."
	@echo "8. composer-install ............................. Install composer dependencies."
	@echo "9. composer-update ............................... Update composer dependencies."
	@echo "10. db-setup ... Setup database (drop existing, create new, migrate migrations)."
	@echo "11. db-purge ........................................ Delete database directory."
	@echo "12. cs-check ................ Check project by php-cs-fixer without any changes."
	@echo "13. cs-fix ........................................ Fix project by php-cs-fixer."
	@echo "14. test ................................................ Execute PhpUnit tests."
	@echo "15. psalm .......................... Check project by psalm without any changes."
	@echo "16. docker-php ....................... Enter to bash shell of php-fpm container."
	@echo "17. docker-pgsql ....................... Enter to bash shell of pgsql container."
	@echo "18. cache-clear ........................................... Clear symfony cache."
	@echo "19. cache-purge ........................................ Delete cache directory."
	@echo "20. lint ............ Fix project by php-cs-fixer and after that check by psalm."
	@echo "21. yt-dlp-update ....................................... Update yt-dlp package."

init: db-remove docker-compose-up composer-install composer-update db-setup supervisor-start cache-clear

restart: docker-compose-down docker-compose-up supervisor-start cache-clear 

supervisor-start:
	docker exec ytdownloader-php-fpm /etc/init.d/supervisor start

supervisor-stop:
	docker exec ytdownloader-php-fpm /etc/init.d/supervisor stop

supervisor-restart:
	docker exec ytdownloader-php-fpm /etc/init.d/supervisor restart

docker-compose-up:
	docker-compose -f docker/docker-compose.yml up -d

docker-compose-down:
	docker-compose -f docker/docker-compose.yml down

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

psalm:
	docker exec ytdownloader-php-fpm vendor/bin/psalm

docker-php:
	docker exec -it ytdownloader-php-fpm bash

docker-pgsql:
	docker exec -it ytdownloader-pgsql bash

cache-clear:
	docker exec ytdownloader-php-fpm php bin/console cache:clear

cache-purge:
	rm -rf ./var/cache/

lint: cs-fix psalm

yt-dlp-update:
	docker exec ytdownloader-php-fpm pip install --upgrade yt-dlp --break-system-packages
