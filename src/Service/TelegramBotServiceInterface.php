<?php

declare(strict_types=1);

namespace App\Service;

use BotMan\BotMan\BotMan;

interface TelegramBotServiceInterface
{
    public function getBot(): BotMan;
}
