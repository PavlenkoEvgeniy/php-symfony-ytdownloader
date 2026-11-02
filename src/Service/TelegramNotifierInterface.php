<?php

declare(strict_types=1);

namespace App\Service;

use BotMan\BotMan\Exceptions\Base\BotManException;

interface TelegramNotifierInterface
{
    /**
     * @throws BotManException
     */
    public function notifyError(string $telegramUserId, string $message): void;

    /**
     * @throws BotManException
     */
    public function notifyFinished(string $telegramUserId, string $message): void;
}
