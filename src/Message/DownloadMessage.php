<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage]
final class DownloadMessage
{
    public function __construct(
        private string $url,
        private string $quality,
        private string $telegramUserId = '',
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getQuality(): string
    {
        return $this->quality;
    }

    public function getTelegramUserId(): string
    {
        return $this->telegramUserId;
    }
}
