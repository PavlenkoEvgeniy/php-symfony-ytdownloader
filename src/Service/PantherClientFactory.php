<?php

namespace App\Service;

use Symfony\Component\Panther\Client;

class PantherClientFactory
{
    private array $chromeOptions;

    public function __construct(array $chromeOptions)
    {
        $this->chromeOptions = $chromeOptions;
    }

    public function createClient(): Client
    {
        $tempDir = sys_get_temp_dir() . '/chrome_profiles/' . uniqid('yt_', true);

        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        return Client::createChromeClient(null, [], [
            'capabilities' => [
                'goog:chromeOptions' => [
                    'args' => [
                        '--headless=new',
                        '--no-sandbox',
                        '--disable-dev-shm-usage',
                        '--disable-gpu',
                        '--remote-debugging-port=9222',
                        '--window-size=1920,1080',
                        '--user-data-dir=' . $tempDir,  // Уникальный каталог для каждого экземпляра
                    ],
                    'binary' => '/usr/bin/google-chrome',
                ],
            ],
        ]);
    }
}
