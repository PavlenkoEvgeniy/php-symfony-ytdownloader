<?php

declare(strict_types=1);

namespace App\Service;

use BotMan\BotMan\Exceptions\Base\BotManException;

final readonly class TelegramNotifier
{
    public function __construct(private TelegramBotService $telegramBotService)
    {
    }

    /**
     * @throws BotManException
     */
    public function notifyError(string $telegramUserId, string $message): void
    {
        $this->telegramBotService->getBot()->say($message, $telegramUserId);
    }

    /**
     * @throws BotManException
     */
    public function notifyFinished(string $telegramUserId, string $message): void
    {
        $this->telegramBotService->getBot()->say($message, $telegramUserId);
    }
}
