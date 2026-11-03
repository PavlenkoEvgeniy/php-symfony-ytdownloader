<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class TelegramProbeResult
{
    public function __construct(
        public bool $isPlaylist,
        public string $title,
        public ?int $duration = null,
    ) {
    }
}
