<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\YoutubeDlWrapper;
use PHPUnit\Framework\TestCase;

final class YoutubeDlWrapperTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            @\unlink($path);
        }

        $this->temporaryPaths = [];

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

    private function wrapper(string $binaryPath, float $timeout): YoutubeDlWrapper
    {
        return new YoutubeDlWrapper($binaryPath, ['--no-warnings'], $timeout, \sys_get_temp_dir());
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
