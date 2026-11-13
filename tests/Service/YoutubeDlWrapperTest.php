<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\YoutubeDlWrapper;
use PHPUnit\Framework\TestCase;

final class YoutubeDlWrapperTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryPaths = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            @\unlink($path);
        }

        foreach ($this->temporaryDirectories as $directory) {
            $this->removeTree($directory);
        }

        $this->temporaryPaths       = [];
        $this->temporaryDirectories = [];

        parent::tearDown();
    }

    /**
     * Verifies a successful download reports its progress and the produced file.
     */
    public function testDownloadReportsProgressAndDownloadedFile(): void
    {
        $file     = $this->createTemporaryFile();
        $binary   = $this->createBinary(\sprintf("echo 'PROGRESS: 42.4%%'\necho %s\n", \escapeshellarg($file)));
        $percents = [];

        $videos = $this->wrapper($binary, 30.0)->download(
            'https://youtube.com/watch?v=test',
            'best_format',
            true,
            true,
            static function (int $percent) use (&$percents): void {
                $percents[] = $percent;
            },
        )->getVideos();

        $this->assertCount(1, $videos);
        $this->assertNull($videos[0]->getError());
        $this->assertSame([42], $percents);
    }

    /**
     * Verifies audio downloads pass the audio extraction options to yt-dlp.
     */
    public function testAudioDownloadPassesAudioOptions(): void
    {
        $argsFile = $this->createTemporaryFile();
        $binary   = $this->createBinary(\sprintf("printf '%%s\\n' \"\$@\" > %s\n", $argsFile));

        $this->wrapper($binary, 30.0)->download('https://youtube.com/watch?v=test', 'best_format', false);

        $arguments = \file($argsFile, FILE_IGNORE_NEW_LINES) ?: [];

        $this->assertContains('--extract-audio', $arguments);
        $this->assertContains('--audio-format', $arguments);
        $this->assertContains('mp3', $arguments);
        $this->assertNotContains('--merge-output-format', $arguments);
    }

    /**
     * Verifies video downloads pass the merge format to yt-dlp.
     */
    public function testVideoDownloadPassesMergeFormat(): void
    {
        $argsFile = $this->createTemporaryFile();
        $binary   = $this->createBinary(\sprintf("printf '%%s\\n' \"\$@\" > %s\n", $argsFile));

        $this->wrapper($binary, 30.0)->download('https://youtube.com/watch?v=test', 'best_format', true);

        $arguments = \file($argsFile, FILE_IGNORE_NEW_LINES) ?: [];

        $this->assertContains('--merge-output-format', $arguments);
        $this->assertContains('mp4', $arguments);
        $this->assertNotContains('--extract-audio', $arguments);
    }

    /**
     * Verifies a failed process exposes the stderr message without the ERROR prefix.
     */
    public function testFailedProcessExposesStderrReason(): void
    {
        $binary = $this->createBinary("echo 'ERROR: video not found' >&2\nexit 1\n");

        $videos = $this->wrapper($binary, 30.0)
            ->download('https://youtube.com/watch?v=test', 'format', true)
            ->getVideos()
        ;

        $this->assertCount(1, $videos);
        $this->assertSame('video not found', $videos[0]->getError());
    }

    /**
     * Verifies a successful process without reported files still reports an error.
     */
    public function testSuccessfulProcessWithoutFilesReportsError(): void
    {
        $binary = $this->createBinary("exit 0\n");

        $videos = $this->wrapper($binary, 30.0)
            ->download('https://youtube.com/watch?v=test', 'format', true)
            ->getVideos()
        ;

        $this->assertCount(1, $videos);
        $this->assertStringContainsString('no downloaded file was reported', (string) $videos[0]->getError());
    }

    /**
     * Verifies reaching the process timeout produces a readable error.
     */
    public function testTimeoutProducesReadableError(): void
    {
        $binary = $this->createBinary("sleep 5\n");

        $videos = $this->wrapper($binary, 0.1)
            ->download('https://youtube.com/watch?v=test', 'format', true)
            ->getVideos()
        ;

        $this->assertCount(1, $videos);
        $this->assertSame('Download timed out after 0 seconds.', $videos[0]->getError());
    }

    /**
     * Verifies null, list, boolean and prefixed options are rendered into the command.
     */
    public function testBuildCommandNormalizesEveryOptionShape(): void
    {
        $wrapper = new YoutubeDlWrapper('/usr/bin/yt-dlp', ['--no-warnings'], 30.0, '/tmp');
        $method  = new \ReflectionMethod(YoutubeDlWrapper::class, 'buildCommand');

        $command = $method->invoke($wrapper, 'https://test.local/v', [
            'output'        => null,
            'subtitle-lang' => ['en', 'de'],
            'yes-playlist'  => true,
            'no-playlist'   => false,
            '--keep-video'  => 'x',
            'format'        => 'best',
        ]);

        $this->assertSame([
            '/usr/bin/yt-dlp',
            '--no-warnings',
            '--subtitle-lang', 'en',
            '--subtitle-lang', 'de',
            '--yes-playlist',
            '--keep-video', 'x',
            '--format', 'best',
            'https://test.local/v',
        ], $command);
    }

    /**
     * Verifies a repeated progress percent is reported only once.
     */
    public function testRepeatedProgressPercentIsReportedOnce(): void
    {
        $file     = $this->createTemporaryFile();
        $binary   = $this->createBinary(\sprintf("echo 'PROGRESS: 42%%'\necho 'PROGRESS: 42%%'\necho %s\n", \escapeshellarg($file)));
        $percents = [];

        $videos = $this->wrapper($binary, 30.0)->download(
            'https://youtube.com/watch?v=test',
            'best_format',
            true,
            true,
            static function (int $percent) use (&$percents): void {
                $percents[] = $percent;
            },
        )->getVideos();

        $this->assertCount(1, $videos);
        $this->assertSame([42], $percents);
    }

    /**
     * Verifies a failing progress callback never breaks the download.
     */
    public function testProgressCallbackFailuresAreIgnored(): void
    {
        $file   = $this->createTemporaryFile();
        $binary = $this->createBinary(\sprintf("echo 'PROGRESS: 42%%'\necho %s\n", \escapeshellarg($file)));

        $videos = $this->wrapper($binary, 30.0)->download(
            'https://youtube.com/watch?v=test',
            'best_format',
            true,
            true,
            static function (int $percent): void {
                throw new \RuntimeException('progress observer is broken');
            },
        )->getVideos();

        $this->assertCount(1, $videos);
        $this->assertNull($videos[0]->getError());
    }

    /**
     * Verifies progress noise on stderr does not hide the failure reason.
     */
    public function testFailedProcessSkipsProgressLinesOnStderr(): void
    {
        $binary = $this->createBinary("echo 'PROGRESS: 50%' >&2\necho 'ERROR: video not found' >&2\nexit 1\n");

        $videos = $this->wrapper($binary, 30.0)
            ->download('https://youtube.com/watch?v=test', 'format', true)
            ->getVideos()
        ;

        $this->assertCount(1, $videos);
        $this->assertSame('video not found', $videos[0]->getError());
    }

    /**
     * Verifies a failed process without output reports the generic reason and no file.
     */
    public function testFailedProcessWithoutOutputReportsGenericReason(): void
    {
        $binary = $this->createBinary("exit 1\n");

        $videos = $this->wrapper($binary, 30.0)
            ->download('https://youtube.com/watch?v=test', 'format', true)
            ->getVideos()
        ;

        $this->assertCount(1, $videos);
        $this->assertSame('yt-dlp process failed.', $videos[0]->getError());
        $this->assertNull($videos[0]->getFile());
    }

    /**
     * Verifies a binary that cannot be started surfaces the process error instead of crashing.
     */
    public function testBinaryThatCannotBeStartedProducesErroredVideo(): void
    {
        $wrapper = new YoutubeDlWrapper('', ['--no-warnings'], 30.0, \sys_get_temp_dir());

        $videos = $wrapper->download('https://youtube.com/watch?v=test', 'best_format', true)->getVideos();

        $this->assertCount(1, $videos);
        $this->assertNotNull($videos[0]->getError());
        $this->assertNull($videos[0]->getFile());
    }

    /**
     * Verifies the produced file exposes its name, directory and size.
     */
    public function testSuccessfulVideoExposesFileDetails(): void
    {
        $file = $this->createTemporaryFile();
        \file_put_contents($file, '12345');

        $binary = $this->createBinary(\sprintf("echo %s\n", \escapeshellarg($file)));

        $videos = $this->wrapper($binary, 30.0)
            ->download('https://youtube.com/watch?v=test', 'best_format', true)
            ->getVideos()
        ;

        $this->assertCount(1, $videos);
        $this->assertNull($videos[0]->getError());

        $info = $videos[0]->getFile();
        $this->assertSame(\basename($file), $info->getBasename());
        $this->assertSame(\dirname($file), $info->getPath());
        $this->assertSame(5.0, $info->getSize());
    }

    /**
     * Verifies an explicit transfer cap is passed to the downloader command.
     */
    public function testMaxFilesizeIsPassedToTheCommand(): void
    {
        $argsFile = $this->createTemporaryFile();
        $binary   = $this->createBinary(\sprintf("printf '%%s\\n' \"\$@\" > %s\n", \escapeshellarg($argsFile)));

        $this->wrapper($binary, 30.0)->download('https://youtube.com/watch?v=test', 'best_format', true, true, null, '2000M');

        $arguments = \file($argsFile, FILE_IGNORE_NEW_LINES) ?: [];

        $this->assertContains('--max-filesize', $arguments);
        $this->assertContains('2000M', $arguments);
    }

    /**
     * Verifies a download without a cap never receives the limit option.
     */
    public function testWithoutMaxFilesizeNoCapOptionIsPassed(): void
    {
        $argsFile = $this->createTemporaryFile();
        $binary   = $this->createBinary(\sprintf("printf '%%s\\n' \"\$@\" > %s\n", \escapeshellarg($argsFile)));

        $this->wrapper($binary, 30.0)->download('https://youtube.com/watch?v=test', 'best_format', true);

        $arguments = \file($argsFile, FILE_IGNORE_NEW_LINES) ?: [];

        $this->assertNotContains('--max-filesize', $arguments);
    }

    /**
     * Verifies a cap refusal that exits 0 without a file reports the refusal line.
     */
    public function testCapRefusalOnSuccessfulExitReportsRefusalReason(): void
    {
        $binary = $this->createBinary("echo '[download] File is larger than max-filesize (2889135013 bytes > 52428800 bytes). Aborting.'\nexit 0\n");

        $videos = $this->wrapper($binary, 30.0)
            ->download('https://youtube.com/watch?v=test', 'format', true)
            ->getVideos()
        ;

        $this->assertCount(1, $videos);
        $this->assertStringContainsString('larger than max-filesize', (string) $videos[0]->getError());
        $this->assertNull($videos[0]->getFile());
    }

    /**
     * Verifies the refusal line wins over unrelated output when the process fails.
     */
    public function testCapRefusalWinsOverOtherFailureReason(): void
    {
        $binary = $this->createBinary("echo 'ERROR: video unavailable' >&2\necho '[download] File is larger than max-filesize (3000 bytes > 1000 bytes). Aborting.'\nexit 1\n");

        $videos = $this->wrapper($binary, 30.0)
            ->download('https://youtube.com/watch?v=test', 'format', true)
            ->getVideos()
        ;

        $this->assertCount(1, $videos);
        $this->assertStringContainsString('larger than max-filesize', (string) $videos[0]->getError());
    }

    /**
     * Verifies an empty successful run under a transfer cap is the cap refusal,
     * because quiet mode keeps yt-dlp from printing the refusal line at all.
     */
    public function testCapRefusalWithoutOutputReportsRefusalReason(): void
    {
        $binary = $this->createBinary("exit 0\n");

        $videos = $this->wrapper($binary, 30.0)
            ->download('https://youtube.com/watch?v=test', 'format', true, true, null, '2000M')
            ->getVideos()
        ;

        $this->assertCount(1, $videos);
        $this->assertStringContainsString('larger than max-filesize', (string) $videos[0]->getError());
        $this->assertNull($videos[0]->getFile());
    }

    /**
     * Verifies an aborted download leaves nothing behind: the staged
     * intermediate is removed with the staging directory.
     */
    public function testAbortedDownloadLeavesNoFilesBehind(): void
    {
        $downloadsDir = $this->createTemporaryDirectory();
        $binary       = $this->createBinary(
            "\$prev=''; \$dir=''; for a in \"\$@\"; do if [ \"\$prev\" = '--paths' ]; then dir=\"\$a\"; fi; prev=\"\$a\"; done\n"
            . "printf staged > \"\$dir/Video-720p.fdash_sep-11.m4a\"\n"
            . "exit 0\n",
        );

        $videos = $this->wrapper($binary, 30.0, $downloadsDir)
            ->download('https://youtube.com/watch?v=test', 'format', true, true, null, '2000M')
            ->getVideos()
        ;

        $this->assertStringContainsString('larger than max-filesize', (string) $videos[0]->getError());
        $this->assertSame([], \array_values(\array_diff((array) \scandir($downloadsDir), ['.', '..'])));
    }

    /**
     * Verifies a finished file is moved out of the staging directory into
     * the downloads directory and reported with its library path.
     */
    public function testSuccessfulDownloadLandsInTheDownloadsDirectory(): void
    {
        $downloadsDir = $this->createTemporaryDirectory();
        $binary       = $this->createBinary(
            "\$prev=''; \$dir=''; for a in \"\$@\"; do if [ \"\$prev\" = '--paths' ]; then dir=\"\$a\"; fi; prev=\"\$a\"; done\n"
            . "printf data > \"\$dir/Video-720p.mp4\"\n"
            . "echo \"\$dir/Video-720p.mp4\"\n",
        );

        $videos = $this->wrapper($binary, 30.0, $downloadsDir)
            ->download('https://youtube.com/watch?v=test', 'format', true)
            ->getVideos()
        ;

        $this->assertNull($videos[0]->getError());

        $info = $videos[0]->getFile();
        $this->assertSame($downloadsDir, $info->getPath());
        $this->assertSame('Video-720p.mp4', $info->getBasename());
        $this->assertFileExists($downloadsDir . '/Video-720p.mp4');
        $this->assertSame(['.', '..', 'Video-720p.mp4'], \scandir($downloadsDir));
    }

    /**
     * Verifies a timed-out download removes its staged partial file.
     */
    public function testTimedOutDownloadLeavesNoFilesBehind(): void
    {
        $downloadsDir = $this->createTemporaryDirectory();
        $binary       = $this->createBinary(
            "\$prev=''; \$dir=''; for a in \"\$@\"; do if [ \"\$prev\" = '--paths' ]; then dir=\"\$a\"; fi; prev=\"\$a\"; done\n"
            . "printf partial > \"\$dir/Video.mp4.part\"\n"
            . "sleep 5\n",
        );

        $videos = $this->wrapper($binary, 0.5, $downloadsDir)
            ->download('https://youtube.com/watch?v=test', 'format', true)
            ->getVideos()
        ;

        $this->assertStringContainsString('timed out', (string) $videos[0]->getError());
        $this->assertSame([], \array_values(\array_diff((array) \scandir($downloadsDir), ['.', '..'])));
    }

    /**
     * Verifies staging directories of interrupted runs are swept by a later
     * run, while a fresh one is kept.
     */
    public function testStaleStagingDirectoriesAreSwept(): void
    {
        $downloadsDir = $this->createTemporaryDirectory();
        $staleDir     = $downloadsDir . '/.work/stale-run';
        $freshDir     = $downloadsDir . '/.work/fresh-run';

        \mkdir($staleDir, 0775, true);
        \file_put_contents($staleDir . '/Video.mp4.part', 'leftover');
        \touch($staleDir, \time() - 7200);

        \mkdir($freshDir, 0775, true);

        $binary = $this->createBinary("exit 0\n");

        $this->wrapper($binary, 30.0, $downloadsDir)->download('https://youtube.com/watch?v=test', 'format', true, true, null, '2000M');

        $this->assertDirectoryDoesNotExist($staleDir);
        $this->assertDirectoryExists($freshDir);
    }

    private function wrapper(string $binaryPath, float $timeout, ?string $downloadsDir = null): YoutubeDlWrapper
    {
        return new YoutubeDlWrapper($binaryPath, ['--no-warnings'], $timeout, $downloadsDir ?? \sys_get_temp_dir());
    }

    private function createTemporaryDirectory(): string
    {
        $path = \sys_get_temp_dir() . '/ytdlp_downloads_' . \bin2hex(\random_bytes(6));

        if (!\mkdir($path, 0775, true)) {
            $this->fail('Cannot create a temporary directory.');
        }

        $this->temporaryDirectories[] = $path;

        return $path;
    }

    private function removeTree(string $directory): void
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
                $this->removeTree($path);

                continue;
            }

            @\unlink($path);
        }

        @\rmdir($directory);
    }

    private function createBinary(string $body): string
    {
        $path = $this->createTemporaryFile();
        \file_put_contents($path, "#!/bin/sh\n" . $body);
        \chmod($path, 0755);

        return $path;
    }

    private function createTemporaryFile(): string
    {
        $path = \tempnam(\sys_get_temp_dir(), 'ytdlp_test_');

        if (false === $path) {
            $this->fail('Cannot create a temporary file.');
        }

        $this->temporaryPaths[] = $path;

        return $path;
    }
}
