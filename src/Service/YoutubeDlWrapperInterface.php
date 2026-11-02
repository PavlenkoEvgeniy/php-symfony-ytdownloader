<?php

declare(strict_types=1);

namespace App\Service;

interface YoutubeDlWrapperInterface
{
    public function download(string $videoUrl, string $downloadFormat, bool $mergeAsVideo): object;
}
