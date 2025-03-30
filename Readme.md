# Небольшой сервис для скачивания видео с youtube

PHP 8, Symfony 7, docker, yt-dlp, norkunas/youtube-dl-php.

## Preview  
<img src="documentation/readmemd-images/1.jpg" alt="Login page" height="300"><img src="documentation/readmemd-images/2.jpg" alt="Login page" height="300"><img src="documentation/readmemd-images/3.jpg" alt="Login page" height="300"><img src="documentation/readmemd-images/4.jpg" alt="Login page" height="300"><img src="documentation/readmemd-images/5.jpg" alt="Login page" height="300">

## Полезное  
1. Запуск проекта:
``` bash
docker-compose -f docker/docker-compose.yaml up -d
docker exec ytdownloader-php-fpm composer install
docker exec ytdownloader-php-fpm composer update
```
Do not forget to add .env.local with mysql login and password, hostname should be 'ytdownloader-mysql'
``` bash
docker exec ytdownloader-php-fpm php bin/console doctrine:database:create
docker exec ytdownloader-php-fpm php bin/console doctrine:migrations:migrate
```
2. Создать нового юзера:
```php
php bin/console user:add <username>
``` 

## Todo:
1. Сделать скачивание видео с ютуба в фоновом режиме (с помощью очередей).
2. Добавить инфу о скачивании видео в фоне.
3. Использование кеша ютуба из браузера для избежания блокировки (ютуб может думать что сервис является ботом).
4. Пофиксить баг с плейлистами - если в имени плейлиста есть спец. символы, то может возникнуть проблема со скачиванием этого плейлиста.
5. Добавить счетчик скачаных видео, статистику.
6. Написать тесты.
7. Рефакторинг (вынести логику из контроллеров в сервисы).
