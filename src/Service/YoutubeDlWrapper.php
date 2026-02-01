<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\VideoDownloaderOption;
use YoutubeDl\Options;
use YoutubeDl\YoutubeDl;

final readonly class YoutubeDlWrapper
{
    public function __construct(private string $downloadsDir)
    {
    }

    /**
     * @return mixed collection returned by YoutubeDl
     */
    public function download(string $videoUrl, string $downloadFormat, bool $mergeAsVideo): mixed
    {
        $yt = new YoutubeDl();

        if ($mergeAsVideo) {
            return $yt->download(
                Options::create()
                    ->downloadPath($this->downloadsDir)
                    ->url(\sprintf('%s --cookies-from-browser chromium+gnomekeyring:/tmp/chromium_data', $videoUrl))
                    ->format($downloadFormat)
                    ->mergeOutputFormat(VideoDownloaderOption::MERGE_OUTPUT_FORMAT_VIDEO->value)
                    ->output(VideoDownloaderOption::OUTPUT_FILE_FORMAT_VIDEO->value)
            );
        }

        return $yt->download(
            Options::create()
                ->downloadPath($this->downloadsDir)
                ->url($videoUrl)
                ->extractAudio(true)
                ->audioFormat(VideoDownloaderOption::FORMAT_AUDIO->value)
                ->output(VideoDownloaderOption::OUTPUT_FILE_FORMAT_AUDIO->value)
        );
    }
}
