<?php

declare(strict_types=1);

namespace App\Service;

interface DownloadSizeEstimatorInterface
{
    /**
     * Estimated final size in bytes per downloadable entry. Entries without a
     * usable size are skipped, so an empty list means "no estimate at all".
     *
     * @return list<int>
     */
    public function estimate(string $videoUrl, string $downloadFormat, bool $allowPlaylist): array;
}
