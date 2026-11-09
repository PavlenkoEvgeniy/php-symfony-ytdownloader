<?php

declare(strict_types=1);

namespace App\Service;

interface TelegramNotifierInterface
{
    public function notifyError(string $telegramUserId, string $message): void;

    public function notifyDownloadFailure(string $telegramUserId, int $taskId): void;
}
