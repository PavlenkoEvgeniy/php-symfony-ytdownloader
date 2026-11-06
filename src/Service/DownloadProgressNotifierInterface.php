<?php

declare(strict_types=1);

namespace App\Service;

interface DownloadProgressNotifierInterface
{
    /**
     * Returns a reporter for a Telegram download, or null when progress is
     * disabled or the task has no chat.
     */
    public function start(int $chatId, string $label): ?ProgressReporterInterface;
}
