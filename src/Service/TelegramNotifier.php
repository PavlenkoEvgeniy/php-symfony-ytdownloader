<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class TelegramNotifier implements TelegramNotifierInterface
{
    public function __construct(
        private TelegramClientInterface $telegramClient,
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * The bot speaks the language the chat's user chose, English otherwise.
     */
    private function localeFor(string $telegramUserId): string
    {
        $user = $this->userRepository->findOneByTelegramUserId((int) $telegramUserId);

        return $user?->getBotLocale() ?? 'en';
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
        // The internal downloader reason stays on the task for the administrator,
        // and the message carries no web-library link on the requester's request.
        $locale = $this->localeFor($telegramUserId);
        $text   = $this->translator->trans('telegram.download_error', [], 'messages', $locale);

        $replyMarkup = [
            'inline_keyboard' => [[
                [
                    'text'          => $this->translator->trans('telegram.retry', [], 'messages', $locale),
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
                $this->translator->trans('telegram.file_too_large', [], 'messages', $this->localeFor($telegramUserId)),
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send a Telegram size rejection message', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
