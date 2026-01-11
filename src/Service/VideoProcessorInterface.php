<?php

declare(strict_types=1);

namespace App\Service;

interface VideoProcessorInterface
{
    public function process(string $videoUrl, string $format, ?string $telegramUserId = null): void;
}
