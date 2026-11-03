<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

final readonly class TelegramNotifier implements TelegramNotifierInterface
{
    public function __construct(
        private TelegramClientInterface $telegramClient,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function notifyError(string $telegramUserId, string $message): void
    {
        try {
            $this->telegramClient->sendMessage((int) $telegramUserId, $message);
        } catch (\Throwable $exception) {
            // A failed notification must not change the download outcome.
            $this->logger->error('Failed to send a Telegram notification', ['error' => $exception->getMessage()]);
        }
    }
}
