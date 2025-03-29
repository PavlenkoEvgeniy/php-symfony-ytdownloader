<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Panther\Client;

class YoutubeAuthService
{
    private string $cookiesPath;

    public function __construct(string $projectDir)
    {
        $this->cookiesPath = $projectDir . '/var/google-chrome/youtube_cookies.txt';
    }

    public function authenticate(string $email, string $password): string
    {
        $client = Client::createChromeClient();

        try {
            // Переходим на YouTube
            $client->request('GET', 'https://www.youtube.com');

            // Ждем кнопку входа и кликаем
            $client->waitFor('#avatar-btn');
            $client->getCrawler()->filter('#avatar-btn')->click();

            // Ждем появление формы входа
            $client->waitFor('input[type="email"]');

            // Заполняем email
            $client->getCrawler()->filter('input[type="email"]')->sendKeys($email);
            $client->getCrawler()->filter('#identifierNext button')->click();

            // Ждем поле пароля
            $client->waitFor('input[type="password"]', 10);

            // Заполняем пароль
            $client->getCrawler()->filter('input[type="password"]')->sendKeys($password);
            $client->getCrawler()->filter('#passwordNext button')->click();

            // Ждем завершения входа (появление аватара)
            $client->waitFor('#avatar-btn', 15);

            // Получаем cookies и сохраняем в файл
            $cookies = $client->getCookieJar()->all();
            $this->saveCookies($cookies);

            return $this->cookiesPath;
        } catch (\Exception $e) {
            throw new \RuntimeException('YouTube authentication failed: ' . $e->getMessage());
        } finally {
            $client->quit();
        }
    }

    private function saveCookies(array $cookies): void
    {
        $fileContent = '';
        foreach ($cookies as $cookie) {
            $fileContent .= $cookie->getName() . '=' . $cookie->getValue() . '; ';
            $fileContent .= 'Domain=' . $cookie->getDomain() . '; ';
            $fileContent .= 'Path=' . $cookie->getPath() . '; ';
            $fileContent .= 'Expires=' . $cookie->getExpires() . '; ';
            $fileContent .= $cookie->isSecure() ? 'Secure; ' : '';
            $fileContent .= $cookie->isHttpOnly() ? 'HttpOnly; ' : '';
            $fileContent .= "\n";
        }

        (new Filesystem())->dumpFile($this->cookiesPath, $fileContent);
    }
}
