<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\DownloadOutcome;

interface VideoProcessorInterface
{
    public function process(string $videoUrl, string $format, ?string $telegramUserId = null): DownloadOutcome;
}
