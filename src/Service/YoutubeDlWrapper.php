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
        private float $stallTimeout = 60.0,
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
        $workDir = $this->prepareWorkDir();

        $options = [
            'paths'             => $workDir ?? $this->downloadsDir,
            'print'             => 'after_move:filepath',
            'yes-playlist'      => $allowPlaylist,
            'no-playlist'       => !$allowPlaylist,
            'newline'           => true,
            // Quiet mode (implied by --print) suppresses the progress bar;
            // --progress turns it back on so progress lines keep flowing.
            'progress'          => true,
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
            try {
                $process->start();

                $lastActivity = \microtime(true);
                $lastState    = $this->stagingState($workDir);

                // The process iterator would only wake up on output, and a
                // stalled run produces none: poll instead, treating output
                // and changes to the staged files as activity. No change for
                // the stall window means the transfer is dead.
                while ($process->isRunning()) {
                    $outChunk = $process->getIncrementalOutput();
                    $errChunk = $process->getIncrementalErrorOutput();

                    if ('' !== $outChunk) {
                        $stdout .= $outChunk;
                        $lastActivity = \microtime(true);

                        if (null !== $onProgress) {
                            $this->consumeProgress($outChunk, $buffer, $onProgress, $lastPercent);
                        }
                    }

                    if ('' !== $errChunk) {
                        $stderr .= $errChunk;
                        $lastActivity = \microtime(true);
                    }

                    $state = $this->stagingState($workDir);

                    if ($state !== $lastState) {
                        $lastState    = $state;
                        $lastActivity = \microtime(true);
                    }

                    if (\microtime(true) - $lastActivity >= $this->stallTimeout) {
                        $process->stop(0);
                        // Files completed before the stall are printed on
                        // stdout: keep them, only the dead transfer is lost.
                        $this->relocateReportedFiles($stdout, $workDir);

                        return $this->createCollection([
                            $this->createErroredVideo(\sprintf(
                                'Download stalled: no changes for %d seconds.',
                                (int) \round($this->stallTimeout),
                            )),
                        ]);
                    }

                    $process->checkTimeout();
                    \usleep(200_000);
                }

                // Final drain: output written between the last poll and the exit.
                $outChunk = $process->getIncrementalOutput();
                $errChunk = $process->getIncrementalErrorOutput();

                $stdout .= $outChunk;
                $stderr .= $errChunk;

                if (null !== $onProgress && '' !== $outChunk) {
                    $this->consumeProgress($outChunk, $buffer, $onProgress, $lastPercent);
                }
            } catch (ProcessTimedOutException) {
                $process->stop(0);
                // Files completed before the timeout are already printed on
                // stdout: relocate them so a partial run keeps finished items.
                $this->relocateReportedFiles($stdout, $workDir);

                return $this->createCollection([
                    $this->createErroredVideo(\sprintf('Download timed out after %d seconds.', (int) $this->timeout)),
                ]);
            } catch (\Throwable $exception) {
                $process->stop(0);
                $this->relocateReportedFiles($stdout, $workDir);

                return $this->createCollection([$this->createErroredVideo($exception->getMessage())]);
            }

            return $this->toCollection($process->getExitCode() ?? 1, $this->relocateReportedFiles($stdout, $workDir), $stderr, $maxFilesize);
        } finally {
            // Whatever the outcome, only finished files reported above survive:
            // partials and intermediates of this run leave with the staging directory.
            if (null !== $workDir) {
                $this->removeDirectory($workDir);
                @\rmdir($this->workRoot());
            }
        }
    }

    /**
     * Creates a private staging directory for this run inside the downloads
     * directory, after sweeping staging directories left by interrupted runs.
     * Returns null when the directory cannot be created, so the run falls
     * back to downloading straight into the downloads directory.
     */
    private function prepareWorkDir(): ?string
    {
        $this->sweepStaleWorkDirs($this->staleAfterSeconds());

        $workDir = $this->workRoot() . DIRECTORY_SEPARATOR . \bin2hex(\random_bytes(8));

        if (!@\mkdir($workDir, 0775, true)) {
            return null;
        }

        return $workDir;
    }

    /**
     * A live run cannot outlive the process timeout, so a staging directory
     * older than twice the timeout (a day without a timeout) is dead weight.
     */
    private function staleAfterSeconds(): int
    {
        if ($this->timeout <= 0) {
            return 86400;
        }

        return (int) \max($this->timeout * 2.0, 3600.0);
    }

    private function sweepStaleWorkDirs(int $staleAfterSeconds): void
    {
        $entries = @\scandir($this->workRoot());

        if (false === $entries) {
            return;
        }

        $threshold = \time() - $staleAfterSeconds;

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path  = $this->workRoot() . DIRECTORY_SEPARATOR . $entry;
            $mtime = @\filemtime($path);

            if (!\is_dir($path) || false === $mtime || $mtime > $threshold) {
                continue;
            }

            $this->removeDirectory($path);
        }
    }

    /**
     * Moves every reported file out of the staging directory into the
     * downloads directory and rewrites its line, so callers only ever see
     * library paths. Lines that are not staged files pass through unchanged.
     */
    private function relocateReportedFiles(string $stdout, ?string $workDir): string
    {
        if (null === $workDir) {
            return $stdout;
        }

        $prefix = $workDir . DIRECTORY_SEPARATOR;
        $lines  = \preg_split('/\r\n|\r|\n/', $stdout) ?: [];

        foreach ($lines as $index => $line) {
            $candidate = \trim($line);

            if ('' === $candidate || !\str_starts_with($candidate, $prefix) || !\is_file($candidate)) {
                continue;
            }

            $target = $this->downloadsDir . DIRECTORY_SEPARATOR . \basename($candidate);

            if (@\rename($candidate, $target)) {
                $lines[$index] = $target;

                continue;
            }

            if (@\copy($candidate, $target)) {
                @\unlink($candidate);
                $lines[$index] = $target;
            }
        }

        return \implode("\n", $lines);
    }

    private function workRoot(): string
    {
        return $this->downloadsDir . DIRECTORY_SEPARATOR . '.work';
    }

    /**
     * Signature of the staging directory: name, size and mtime of every
     * entry. A transfer that keeps writing changes it; a stalled one does not.
     * Returns an empty string when there is no isolated directory to watch.
     */
    private function stagingState(?string $workDir): string
    {
        if (null === $workDir) {
            return '';
        }

        // The worker is long-running: without dropping the stat cache every
        // poll would see the sizes and mtimes of the first observation.
        \clearstatcache();

        $entries = @\scandir($workDir);

        if (false === $entries) {
            return '';
        }

        $state = [];

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path  = $workDir . DIRECTORY_SEPARATOR . $entry;
            $mtime = @\filemtime($path);
            $size  = @\filesize($path);

            $state[] = $entry . '|' . (false === $size ? -1 : $size) . '|' . (false === $mtime ? -1 : $mtime);
        }

        return \implode("\n", $state);
    }

    private function removeDirectory(string $directory): void
    {
        $entries = @\scandir($directory);

        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            if (\is_dir($path) && !\is_link($path)) {
                $this->removeDirectory($path);

                continue;
            }

            @\unlink($path);
        }

        @\rmdir($directory);
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
