<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\VideoDownloaderOption;
use P3s\YtDlp\ProcessResult;
use P3s\YtDlp\YtDlpClientInterface;

final readonly class YoutubeDlWrapper
{
    public function __construct(
        private YtDlpClientInterface $ytDlp,
        private string $downloadsDir,
    ) {
    }

    /**
     * @return object collection with getVideos() method
     */
    public function download(string $videoUrl, string $downloadFormat, bool $mergeAsVideo): object
    {
        $options = [
            'paths'        => $this->downloadsDir,
            'print'        => 'after_move:filepath',
            'yes-playlist' => true,
            'write-auto-subs' => true,
        ];

        if ($mergeAsVideo) {
            $result = $this->ytDlp->download($videoUrl, $options + [
                'cookies-from-browser' => 'chromium+gnomekeyring:/tmp/chromium_data',
                'format'               => $downloadFormat,
                'merge-output-format'  => VideoDownloaderOption::MERGE_OUTPUT_FORMAT_VIDEO->value,
                'output'               => VideoDownloaderOption::OUTPUT_FILE_FORMAT_VIDEO->value,
            ]);

            return $this->toCollection($result);
        }

        $result = $this->ytDlp->download($videoUrl, $options + [
            'extract-audio' => true,
            'audio-format'  => VideoDownloaderOption::FORMAT_AUDIO->value,
            'output'        => VideoDownloaderOption::OUTPUT_FILE_FORMAT_AUDIO->value,
        ]);

        return $this->toCollection($result);
    }

    private function toCollection(ProcessResult $result): object
    {
        if (!$result->isSuccessful()) {
            $error = \trim($result->stderr);

            if ('' === $error) {
                $error = \trim($result->stdout);
            }

            if ('' === $error) {
                $error = 'yt-dlp process failed.';
            }

            return $this->createCollection([$this->createErroredVideo($error)]);
        }

        $videos = [];

        foreach ($this->extractDownloadedPaths($result->stdout) as $downloadedPath) {
            $videos[] = $this->createSuccessfulVideo($downloadedPath);
        }

        if ([] === $videos) {
            return $this->createCollection([$this->createErroredVideo('yt-dlp finished successfully, but no downloaded file was reported.')]);
        }

        return $this->createCollection($videos);
    }

    /**
     * @return list<string>
     */
    private function extractDownloadedPaths(string $stdout): array
    {
        $lines = \preg_split('/\r\n|\r|\n/', $stdout) ?: [];
        $paths = [];

        foreach ($lines as $line) {
            $candidate = \trim($line);

            if ('' === $candidate || !\is_file($candidate)) {
                continue;
            }

            $paths[] = $candidate;
        }

        return \array_values(\array_unique($paths));
    }

    /**
     * @param list<object> $videos
     */
    private function createCollection(array $videos): object
    {
        return new class($videos) {
            /**
             * @param list<object> $videos
             */
            public function __construct(private array $videos)
            {
            }

            /**
             * @return list<object>
             */
            public function getVideos(): array
            {
                return $this->videos;
            }
        };
    }

    private function createErroredVideo(string $error): object
    {
        return new class($error) {
            public function __construct(private string $error)
            {
            }

            public function getError(): string
            {
                return $this->error;
            }

            public function getFile(): null
            {
                return null;
            }
        };
    }

    private function createSuccessfulVideo(string $downloadedPath): object
    {
        $fileInfo = new \SplFileInfo($downloadedPath);
        $size     = $fileInfo->getSize();

        return new class($fileInfo, false === $size ? 0.0 : (float) $size) {
            public function __construct(private \SplFileInfo $fileInfo, private float $size)
            {
            }

            public function getError(): null
            {
                return null;
            }

            public function getFile(): object
            {
                return new class($this->fileInfo, $this->size) {
                    public function __construct(private \SplFileInfo $fileInfo, private float $size)
                    {
                    }

                    public function getBasename(): string
                    {
                        return $this->fileInfo->getBasename();
                    }

                    public function getPath(): string
                    {
                        $path = $this->fileInfo->getPath();

                        return '' === $path ? '.' : $path;
                    }

                    public function getSize(): float
                    {
                        return $this->size;
                    }
                };
            }
        };
    }
}
