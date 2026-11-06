<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\DownloadResult;

interface VideoProcessorInterface
{
    public function process(string $videoUrl, string $format, ?string $telegramUserId = null, ?int $taskId = null): DownloadResult;
}
