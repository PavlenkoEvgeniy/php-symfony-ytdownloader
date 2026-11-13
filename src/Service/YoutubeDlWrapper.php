<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\VideoDownloaderOption;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Runs yt-dlp itself instead of the blocking package client, so progress can be
 * observed while the process runs. The Telegram probe keeps using the package
 * client with its short timeout.
 */
final readonly class YoutubeDlWrapper implements YoutubeDlWrapperInterface
{
    public function __construct(
        #[Autowire('%yt_dlp.binary_path%')]
        private string $binaryPath,
        /** @var list<string> */
        #[Autowire('%yt_dlp.default_arguments%')]
        private array $defaultArguments,
        #[Autowire('%yt_dlp.timeout%')]
        private float $timeout,
        #[Autowire(env: 'string:resolve:APP_DOWNLOADS_DIR')]
        private string $downloadsDir,
    ) {
    }

    #[\Override]
    public function download(
        string $videoUrl,
        string $downloadFormat,
        bool $mergeAsVideo,
        bool $allowPlaylist = true,
        ?\Closure $onProgress = null,
        ?string $maxFilesize = null,
    ): object {
        $options = [
            'paths'             => $this->downloadsDir,
            'print'             => 'after_move:filepath',
            'yes-playlist'      => $allowPlaylist,
            'no-playlist'       => !$allowPlaylist,
            'newline'           => true,
            'progress-template' => 'download:PROGRESS:%(progress._percent_str)s',
        ];

        if (null !== $maxFilesize) {
            $options['max-filesize'] = $maxFilesize;
        }

        if ($mergeAsVideo) {
            $options += [
                'cookies-from-browser' => 'chromium+gnomekeyring:/tmp/chromium_data',
                'format'               => $downloadFormat,
                'merge-output-format'  => VideoDownloaderOption::MERGE_OUTPUT_FORMAT_VIDEO->value,
                'output'               => VideoDownloaderOption::OUTPUT_FILE_FORMAT_VIDEO->value,
            ];
        } else {
            $options += [
                'cookies-from-browser' => 'chromium+gnomekeyring:/tmp/chromium_data',
                'extract-audio'        => true,
                'audio-format'         => VideoDownloaderOption::FORMAT_AUDIO->value,
                'output'               => VideoDownloaderOption::OUTPUT_FILE_FORMAT_AUDIO->value,
            ];
        }

        $process = new Process(
            $this->buildCommand($videoUrl, $options),
            null,
            null,
            null,
            $this->timeout > 0 ? $this->timeout : null,
        );

        $stdout      = '';
        $stderr      = '';
        $buffer      = '';
        $lastPercent = -1;

        try {
            $process->start();

            foreach ($process as $type => $data) {
                if (Process::OUT === $type) {
                    $stdout .= $data;
                } else {
                    $stderr .= $data;
                }

                if (null !== $onProgress) {
                    $this->consumeProgress($data, $buffer, $onProgress, $lastPercent);
                }
            }
        } catch (ProcessTimedOutException) {
            $process->stop(0);

            return $this->createCollection([
                $this->createErroredVideo(\sprintf('Download timed out after %d seconds.', (int) $this->timeout)),
            ]);
        } catch (\Throwable $exception) {
            $process->stop(0);

            return $this->createCollection([$this->createErroredVideo($exception->getMessage())]);
        }

        return $this->toCollection($process->getExitCode() ?? 1, $stdout, $stderr, $maxFilesize);
    }

    private function toCollection(int $exitCode, string $stdout, string $stderr, ?string $maxFilesize): object
    {
        // A transfer-cap refusal exits 0 like a skipped download, so it must be
        // recognized before the exit-code and file bookkeeping below.
        $refusal = $this->maxFilesizeRefusal($stderr, $stdout);

        if (null !== $refusal) {
            return $this->createCollection([$this->createErroredVideo($refusal)]);
        }

        if (0 !== $exitCode) {
            return $this->createCollection([$this->createErroredVideo($this->failureReason($stderr, $stdout))]);
        }

        $videos = [];

        foreach ($this->extractDownloadedPaths($stdout) as $downloadedPath) {
            $videos[] = $this->createSuccessfulVideo($downloadedPath);
        }

        if ([] === $videos) {
            // --print forces yt-dlp into quiet mode, which suppresses the
            // refusal line, so an empty successful run under a transfer cap
            // is that refusal and must be recognized by outcome instead.
            if (null !== $maxFilesize) {
                return $this->createCollection([
                    $this->createErroredVideo(\sprintf('File is larger than max-filesize (transfer cap %s).', $maxFilesize)),
                ]);
            }

            return $this->createCollection([
                $this->createErroredVideo('yt-dlp finished successfully, but no downloaded file was reported.'),
            ]);
        }

        return $this->createCollection($videos);
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $options
     *
     * @return list<string>
     */
    private function buildCommand(string $videoUrl, array $options): array
    {
        $command = [$this->binaryPath];

        foreach ($this->defaultArguments as $argument) {
            $command[] = $argument;
        }

        foreach ($options as $option => $value) {
            $optionName = $this->normalizeOptionName($option);

            if (null === $value) {
                continue;
            }

            if (\is_array($value)) {
                foreach ($value as $item) {
                    $command[] = $optionName;
                    $command[] = (string) $item;
                }

                continue;
            }

            if (\is_bool($value)) {
                if ($value) {
                    $command[] = $optionName;
                }

                continue;
            }

            $command[] = $optionName;
            $command[] = (string) $value;
        }

        $command[] = $videoUrl;

        return $command;
    }

    private function normalizeOptionName(string $option): string
    {
        $trimmed = \ltrim($option);

        if (\str_starts_with($trimmed, '--') || \str_starts_with($trimmed, '-')) {
            return $trimmed;
        }

        return '--' . \ltrim($trimmed, '-');
    }

    private function consumeProgress(string $chunk, string &$buffer, \Closure $onProgress, int &$lastPercent): void
    {
        $buffer .= $chunk;
        $parts  = \preg_split('/\r\n|\r|\n/', $buffer) ?: [];
        $buffer = (string) \array_pop($parts);

        foreach ($parts as $line) {
            if (1 !== \preg_match('/PROGRESS:\s*([0-9]+(?:\.[0-9]+)?)\s*%/', $line, $matches)) {
                continue;
            }

            $percent = \max(0, \min(100, (int) \round((float) $matches[1])));

            if ($percent === $lastPercent) {
                continue;
            }

            $lastPercent = $percent;

            try {
                $onProgress($percent);
            } catch (\Throwable) {
                // Progress is best-effort and must never break the download.
            }
        }
    }

    /**
     * @return string[]
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

    private function maxFilesizeRefusal(string ...$outputs): ?string
    {
        foreach ($outputs as $output) {
            foreach (\preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
                $line = \trim($line);

                if ('' === $line || !\str_contains($line, 'larger than max-filesize')) {
                    continue;
                }

                return \mb_substr(\preg_replace('/^ERROR:\s*/i', '', $line) ?? $line, 0, 500);
            }
        }

        return null;
    }

    private function failureReason(string $stderr, string $stdout): string
    {
        foreach ([$stderr, $stdout] as $output) {
            foreach (\preg_split('/\r\n|\r|\n/', \trim($output)) ?: [] as $line) {
                $line = \trim(\preg_replace('/^ERROR:\s*/i', '', \trim($line)) ?? '');

                if ('' === $line || \str_starts_with($line, 'PROGRESS:')) {
                    continue;
                }

                return \mb_substr($line, 0, 500);
            }
        }

        return 'yt-dlp process failed.';
    }

    /**
     * @param object[] $videos
     */
    private function createCollection(array $videos): object
    {
        return new readonly class($videos) {
            /**
             * @param object[] $videos
             */
            public function __construct(
                private array $videos,
            ) {
            }

            /**
             * @return object[]
             */
            public function getVideos(): array
            {
                return $this->videos;
            }
        };
    }

    private function createErroredVideo(string $error): object
    {
        return new readonly class($error) {
            public function __construct(
                private string $error,
            ) {
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
        $size     = (float) (false === $size ? 0 : $size);

        return new readonly class($fileInfo, $size) {
            public function __construct(
                private \SplFileInfo $fileInfo,
                private float $size,
            ) {
            }

            public function getError(): null
            {
                return null;
            }

            public function getFile(): object
            {
                return new readonly class($this->fileInfo, $this->size) {
                    public function __construct(
                        private \SplFileInfo $fileInfo,
                        private float $size,
                    ) {
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
