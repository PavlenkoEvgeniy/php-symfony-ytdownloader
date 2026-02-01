<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\VideoDownloaderOption;

final readonly class FormatResolver
{
    /**
     * @return array{string, bool} [downloadFormat, mergeAsVideo]
     */
    public function resolve(string $format): array
    {
        return match ($format) {
            'best'     => [VideoDownloaderOption::BEST_VIDEO_DOWNLOAD_FORMAT->value, true],
            'moderate' => [VideoDownloaderOption::MODERATE_VIDEO_DOWNLOAD_FORMAT->value, true],
            'poor'     => [VideoDownloaderOption::POOR_VIDEO_DOWNLOAD_FORMAT->value, true],
            'audio'    => [VideoDownloaderOption::NO_VIDEO_DOWNLOAD_FORMAT->value, false],
            default    => [VideoDownloaderOption::BEST_VIDEO_DOWNLOAD_FORMAT->value, true],
        };
    }
}
