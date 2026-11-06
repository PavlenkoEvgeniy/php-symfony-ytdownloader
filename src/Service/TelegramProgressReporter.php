<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Edits one Telegram message at most once per interval. The first message is
 * created only when the first interval elapses, so short downloads stay silent.
 */
final class TelegramProgressReporter implements ProgressReporterInterface
{
    private readonly \DateTimeImmutable $startedAt;
    private ?\DateTimeImmutable $lastSentAt = null;
    private ?int $messageId                 = null;
    private int $lastPercent                = -1;

    public function __construct(
        private readonly TelegramClientInterface $telegramClient,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
        private readonly int $chatId,
        private readonly string $label,
        private readonly int $intervalSeconds,
    ) {
        $this->startedAt = $clock->now();
    }

    #[\Override]
    public function report(int $percent): void
    {
        $percent = \max(0, \min(100, $percent));

        if ($percent === $this->lastPercent) {
            return;
        }

        $now = $this->clock->now();

        if (null === $this->messageId) {
            if (100 === $percent || $now < $this->startedAt->modify(\sprintf('+%d seconds', $this->intervalSeconds))) {
                return;
            }
        } elseif (100 !== $percent
            && null !== $this->lastSentAt
            && $now < $this->lastSentAt->modify(\sprintf('+%d seconds', $this->intervalSeconds))
        ) {
            return;
        }

        if ($this->sendOrEdit($this->text($percent))) {
            $this->lastPercent = $percent;
            $this->lastSentAt  = $now;
        }
    }

    private function text(int $percent): string
    {
        $label = \mb_strlen($this->label) > 80
            ? \mb_substr($this->label, 0, 77) . '…'
            : $this->label;

        return \sprintf(
            '%s %d%%',
            $this->translator->trans('telegram.progress', ['%title%' => $label]),
            $percent,
        );
    }

    private function sendOrEdit(string $text): bool
    {
        try {
            if (null === $this->messageId) {
                return $this->send($text);
            }

            $this->telegramClient->editMessageText($this->chatId, $this->messageId, $text);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to update Telegram download progress', [
                'error' => $exception->getMessage(),
            ]);

            return $this->send($text);
        }
    }

    private function send(string $text): bool
    {
        try {
            $messageId = $this->telegramClient->sendMessage($this->chatId, $text);
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to send Telegram download progress', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if ($messageId <= 0) {
            return false;
        }

        $this->messageId = $messageId;

        return true;
    }
}
