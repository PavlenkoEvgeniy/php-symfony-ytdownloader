<?php

declare(strict_types=1);

namespace App\Service;

use BotMan\BotMan\BotMan;

interface TelegramMessageHandlerInterface
{
    public function onStart(BotMan $bot): void;

    public function onUrl(BotMan $bot, string $url): void;
}
