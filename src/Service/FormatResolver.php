<?php

declare(strict_types=1);

namespace App\Service;

final readonly class FormatResolver
{
    /**
     * @return array{string, bool} [downloadFormat, mergeAsVideo]
     */
    public function resolve(string $format): array
    {
        return match ($format) {
            'best'     => [VideoDownloaderService::BEST_VIDEO_DOWNLOAD_FORMAT, true],
            'moderate' => [VideoDownloaderService::MODERATE_VIDEO_DOWNLOAD_FORMAT, true],
            'poor'     => [VideoDownloaderService::POOR_VIDEO_DOWNLOAD_FORMAT, true],
            'audio'    => [VideoDownloaderService::NO_VIDEO_DOWNLOAD_FORMAT, false],
            default    => [VideoDownloaderService::BEST_VIDEO_DOWNLOAD_FORMAT, true],
        };
    }
}
