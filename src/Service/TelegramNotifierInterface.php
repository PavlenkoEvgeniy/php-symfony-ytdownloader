<?php

declare(strict_types=1);

namespace App\Service;

interface TelegramNotifierInterface
{
    public function notifyError(string $telegramUserId, string $message): void;

    public function notifyDownloadFailure(string $telegramUserId, int $taskId): void;

    /**
     * Reports a pre-transfer size rejection: the actionable exception to the
     * generic failure message (ADR-0017), with no Re-download action.
     */
    public function notifyFileTooLarge(string $telegramUserId): void;
}
