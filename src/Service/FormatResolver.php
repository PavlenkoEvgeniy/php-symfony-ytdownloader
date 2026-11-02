<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\DownloadQuality;
use App\Enum\VideoDownloaderOption;

final readonly class FormatResolver implements FormatResolverInterface
{
    #[\Override]
    /**
     * @return array{string, bool} [downloadFormat, mergeAsVideo]
     */
    public function resolve(string $format): array
    {
        $quality = DownloadQuality::tryFrom($format) ?? DownloadQuality::BEST;

        return match ($quality) {
            DownloadQuality::BEST     => [VideoDownloaderOption::BEST_VIDEO_DOWNLOAD_FORMAT->value, true],
            DownloadQuality::MODERATE => [VideoDownloaderOption::MODERATE_VIDEO_DOWNLOAD_FORMAT->value, true],
            DownloadQuality::POOR     => [VideoDownloaderOption::POOR_VIDEO_DOWNLOAD_FORMAT->value, true],
            DownloadQuality::AUDIO    => [VideoDownloaderOption::NO_VIDEO_DOWNLOAD_FORMAT->value, false],
        };
    }
}
