<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\DownloadSettingsRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DownloadProgressNotifier implements DownloadProgressNotifierInterface
{
    public function __construct(
        private TelegramClientInterface $telegramClient,
        private DownloadSettingsRepositoryInterface $settingsRepository,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function start(int $chatId, string $label): ?ProgressReporterInterface
    {
        if ($chatId <= 0) {
            return null;
        }

        try {
            $intervalMinutes = $this->settingsRepository->getSettings()->getProgressIntervalMinutes();
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to read the progress interval', ['error' => $exception->getMessage()]);

            return null;
        }

        if ($intervalMinutes <= 0) {
            return null;
        }

        return new TelegramProgressReporter(
            $this->telegramClient,
            $this->translator,
            $this->logger,
            $this->clock,
            $chatId,
            $label,
            $intervalMinutes * 60,
        );
    }
}
