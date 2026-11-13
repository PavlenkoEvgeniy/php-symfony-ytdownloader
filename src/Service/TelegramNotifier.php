<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class TelegramNotifier implements TelegramNotifierInterface
{
    public function __construct(
        private TelegramClientInterface $telegramClient,
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
        #[Autowire(env: 'string:TELEGRAM_HOST_URL')]
        private string $telegramHostUrl,
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

    #[\Override]
    public function notifyDownloadFailure(string $telegramUserId, int $taskId): void
    {
        $libraryUrl = \rtrim($this->telegramHostUrl, '/') . '/ui/source';
        $text       = \sprintf(
            "%s\n%s",
            // The internal downloader reason stays on the task for the administrator.
            $this->translator->trans('telegram.download_error'),
            $libraryUrl,
        );

        $replyMarkup = [
            'inline_keyboard' => [[
                [
                    'text'          => $this->translator->trans('telegram.retry'),
                    'callback_data' => \sprintf('retry:%d', $taskId),
                ],
            ]],
        ];

        try {
            $this->telegramClient->sendMessage((int) $telegramUserId, $text, $replyMarkup);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send a Telegram failure notification', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    #[\Override]
    public function notifyFileTooLarge(string $telegramUserId): void
    {
        try {
            $this->telegramClient->sendMessage(
                (int) $telegramUserId,
                $this->translator->trans('telegram.file_too_large'),
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send a Telegram size rejection message', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
