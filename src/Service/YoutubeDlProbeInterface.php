<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\TelegramProbeResult;

interface YoutubeDlProbeInterface
{
    public function probe(string $url): TelegramProbeResult;
}
