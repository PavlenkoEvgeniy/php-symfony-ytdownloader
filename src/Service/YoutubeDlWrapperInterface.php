<?php

declare(strict_types=1);

namespace App\Service;

interface YoutubeDlWrapperInterface
{
    /**
     * @param (\Closure(int): void)|null $onProgress
     * @param string|null                $maxFilesize Transfer cap (e.g. "2000M"); null disables it
     * @param (\Closure(): bool)|null    $shouldAbort Polled while the process runs; true stops it
     */
    public function download(
        string $videoUrl,
        string $downloadFormat,
        bool $mergeAsVideo,
        bool $allowPlaylist = true,
        ?\Closure $onProgress = null,
        ?string $maxFilesize = null,
        ?\Closure $shouldAbort = null,
    ): object;
}
