<?php

declare(strict_types=1);

namespace App\Service;

interface YoutubeDlWrapperInterface
{
    /**
     * @param (\Closure(int): void)|null $onProgress
     */
    public function download(
        string $videoUrl,
        string $downloadFormat,
        bool $mergeAsVideo,
        bool $allowPlaylist = true,
        ?\Closure $onProgress = null,
    ): object;
}
