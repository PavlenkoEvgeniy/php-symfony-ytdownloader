<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage]
final readonly class DownloadMessage
{
    public function __construct(
        public string $url,
        public string $quality,
        public string $telegramUserId = '',
        public ?int $taskId = null,
    ) {
    }
}
