<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Panther\Client;

class YoutubeAuthService
{
    private string $cookiesPath;

    public function __construct(string $projectDir,  private Client $client)
    {
        $this->cookiesPath = $projectDir . '/var/google-chrome/youtube_cookies.txt';
    }

    public function authenticate(string $email, string $password): string
    {
        $tempDir = $this->createTempProfileDir();

        $client = Client::createChromeClient(null, [], [
            'capabilities' => [
                'goog:chromeOptions' => [
                    'args' => [
                        '--headless=new',
                        '--no-sandbox',
                        '--disable-dev-shm-usage',
                        '--disable-gpu',
                        '--remote-debugging-port=9222',
                        '--window-size=1920,1080',
                        '--user-data-dir=' . $tempDir
                    ]
                ]
            ]
        ]);

        try {
            // Переходим на YouTube
            $this->client->request('GET', 'https://www.youtube.com');

            // Ждем кнопку входа и кликаем
            $this->client->waitFor('#avatar-btn');
            $this->client->getCrawler()->filter('#avatar-btn')->click();

            // Ждем появление формы входа
            $this->client->waitFor('input[type="email"]');

            // Заполняем email
            $this->client->getCrawler()->filter('input[type="email"]')->sendKeys($email);
            $this->client->getCrawler()->filter('#identifierNext button')->click();

            // Ждем поле пароля
            $this->client->waitFor('input[type="password"]', 10);

            // Заполняем пароль
            $this->client->getCrawler()->filter('input[type="password"]')->sendKeys($password);
            $this->client->getCrawler()->filter('#passwordNext button')->click();

            // Ждем завершения входа (появление аватара)
            $this->client->waitFor('#avatar-btn', 15);

            // Получаем cookies и сохраняем в файл
            $cookies = $this->client->getCookieJar()->all();
            $this->saveCookies($cookies);

            return $this->cookiesPath;
        } catch (\Exception $e) {
            throw new \RuntimeException('YouTube authentication failed: ' . $e->getMessage());
        } finally {
            $client->quit();
            $this->removeTempDir($tempDir); // Очистка временных файлов
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

    private function createTempProfileDir(): string
    {
        $tempDir = sys_get_temp_dir() . '/chrome_yt_' . uniqid();
        mkdir($tempDir, 0777, true);
        return $tempDir;
    }
    
    private function removeTempDir(string $dir): void
    {
        (new Filesystem())->remove($dir);
    }
}
