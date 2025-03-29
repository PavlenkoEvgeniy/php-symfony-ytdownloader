<?php

namespace App\Service;

use Symfony\Component\Panther\Client;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;

class YoutubeAuthService
{
    private string $cookiesPath;
    private string $chromeProfileBaseDir;
    
    public function __construct(string $projectDir, ?string $chromeProfileBaseDir = null)
    {
        $this->cookiesPath = $projectDir.'/var/youtube_cookies.txt';
        $this->chromeProfileBaseDir = $chromeProfileBaseDir ?: sys_get_temp_dir().'/chrome_profiles';
    }

    public function authenticate(string $email, string $password): string
    {
        $profileDir = $this->createProfileDir();

        try {
            $client = $this->createChromeClient($profileDir);
            
            // Процесс аутентификации
            $client->request('GET', 'https://www.youtube.com');
            $client->waitFor('#avatar-btn', 10);
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
            
            $this->saveCookies($client->getCookieJar()->all());
            return $this->cookiesPath;
            
        } finally {
            if (isset($client)) {
                $client->quit();
            }
            // $this->cleanProfileDir($profileDir);
        }
    }

    private function createChromeClient(string $profileDir): Client
    {
        return Client::createChromeClient(null, [], [
            'capabilities' => [
                'goog:chromeOptions' => [
                    'args' => [
                        '--headless=new',
                        '--no-sandbox',
                        '--disable-dev-shm-usage',
                        '--window-size=1920,1080',
                        '--user-data-dir='.$profileDir,
                        '--remote-debugging-port='.rand(9222, 9322)
                    ]
                ]
            ]
        ]);
    }

    private function createProfileDir(): string
    {
        $dir = $this->chromeProfileBaseDir.'/'.uniqid('yt_', true);
        (new Filesystem())->mkdir($dir, 0777);
        
        $process = new Process(['chown', '-R', 'www-data:www-data', $dir]);
        $process->run();
        return $dir;
    }

    private function cleanProfileDir(string $dir): void
    {
        (new Filesystem())->remove($dir);
    }

    private function saveCookies(array $cookies): void
    {
        $content = '';
        foreach ($cookies as $cookie) {
            $content .= "{$cookie->getName()}={$cookie->getValue()}\n";
        }
        file_put_contents($this->cookiesPath, $content);
    }
}