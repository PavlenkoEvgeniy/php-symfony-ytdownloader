<?php

declare(strict_types=1);

namespace App\Service;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Telegram\TelegramDriver;

final class TelegramBotService
{
    public function __construct(
        private readonly string $telegramBotToken,
    ) {
    }

    public function getBot(): BotMan
    {
        $config = [
            'telegram' => [
                'token' => $this->telegramBotToken,
            ],
        ];

        DriverManager::loadDriver(TelegramDriver::class);
        $botman = BotManFactory::create($config);
        $botman->loadDriver(TelegramDriver::class);

        return $botman;
    }
}
