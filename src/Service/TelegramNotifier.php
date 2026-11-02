<?php

declare(strict_types=1);

namespace App\Service;

use BotMan\BotMan\Exceptions\Base\BotManException;

final readonly class TelegramNotifier implements TelegramNotifierInterface
{
    public function __construct(
        private TelegramBotServiceInterface $telegramBotService,
    ) {
    }

    #[\Override]
    /**
     * @throws BotManException
     */
    public function notifyError(string $telegramUserId, string $message): void
    {
        $this->telegramBotService->getBot()->say($message, $telegramUserId);
    }

    #[\Override]
    /**
     * @throws BotManException
     */
    public function notifyFinished(string $telegramUserId, string $message): void
    {
        $this->telegramBotService->getBot()->say($message, $telegramUserId);
    }
}
