<?php

declare(strict_types=1);

namespace App\Service;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Telegram\TelegramDriver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class TelegramBotService implements TelegramBotServiceInterface
{
    public function __construct(
        #[Autowire(env: 'string:TELEGRAM_BOT_TOKEN')]
        private string $telegramBotToken,
    ) {
    }

    #[\Override]
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
