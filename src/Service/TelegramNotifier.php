<?php

declare(strict_types=1);

namespace App\Service;

final readonly class TelegramNotifier
{
    public function __construct(private TelegramBotService $telegramBotService)
    {
    }

    public function notifyError(string $telegramUserId, string $message): void
    {
        $this->telegramBotService->getBot()->say($message, $telegramUserId);
    }

    public function notifyFinished(string $telegramUserId, string $message): void
    {
        $this->telegramBotService->getBot()->say($message, $telegramUserId);
    }
}
