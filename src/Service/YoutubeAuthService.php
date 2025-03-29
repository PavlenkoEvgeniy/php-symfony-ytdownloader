<?php

namespace App\Service;

use Symfony\Component\Panther\Client;

class YoutubeAuthService
{
    public function __construct(
        private BrowserProfileManager $profileManager,
        private string $projectDir
    ) {}

    public function authenticate(string $email, string $password): string
    {
        $profileDir = $this->profileManager->createProfile();
        $cookiesPath = $profileDir . '/' . 'youtube_cookies.txt';
        
        try {
            $client = Client::createChromeClient(null, [], [
                'capabilities' => [
                    'goog:chromeOptions' => [
                        'args' => [
                            '--headless=new',
                            '--no-sandbox',
                            '--disable-dev-shm-usage',
                            '--window-size=1920,1080',
                            '--user-data-dir='.$profileDir,
                            '--remote-debugging-port='.rand(9200, 9299)
                        ]
                    ]
                ]
            ]);

            dd(111);
            
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
            
            $this->saveCookies($client->getCookieJar()->all(), $cookiesPath);
            return $cookiesPath;
            
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

    private function saveCookies(array $cookies, string $cookiesPath): void
    {
        $content = '';
        foreach ($cookies as $cookie) {
            $content .= "{$cookie->getName()}={$cookie->getValue()}\n";
        }
        file_put_contents($cookiesPath, $content);
    }
}