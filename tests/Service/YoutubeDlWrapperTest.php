<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\VideoDownloaderOption;
use App\Service\YoutubeDlWrapper;
use P3s\YtDlp\ProcessResult;
use P3s\YtDlp\YtDlpClientInterface;
use PHPUnit\Framework\TestCase;

final class YoutubeDlWrapperTest extends TestCase
{
    private YoutubeDlWrapper $wrapper;
    private YtDlpClientInterface $ytDlp;
    private string $downloadsDir;

    protected function setUp(): void
    {
        $this->downloadsDir = '/tmp/downloads';

        $this->ytDlp   = $this->createMock(YtDlpClientInterface::class);
        $this->wrapper = new YoutubeDlWrapper($this->ytDlp, $this->downloadsDir);
    }

    /**
     * Verifies video downloads pass the video merge format to yt-dlp.
     */
    public function testDownloadVideoCallsYtDlpWithVideoOptions(): void
    {
        $processResult = $this->createMockProcessResult(true, '', '');

        /** @phpstan-ignore-next-line */
        $this->ytDlp->expects($this->once())
            ->method('download')
            ->with(
                'https://youtube.com/watch?v=test',
                $this->callback(static fn (array $options) => isset($options['merge-output-format'])
                    && $options['merge-output-format'] === VideoDownloaderOption::MERGE_OUTPUT_FORMAT_VIDEO->value)
            )
            ->willReturn($processResult);

        $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'best_format', true);

        $this->assertTrue(\method_exists($result, 'getVideos'));
    }

    /**
     * Verifies audio downloads request audio extraction from yt-dlp.
     */
    public function testDownloadAudioCallsYtDlpWithAudioOptions(): void
    {
        $processResult = $this->createMockProcessResult(true, '', '');
        /** @phpstan-ignore-next-line */
        $this->ytDlp->expects($this->once())
                    ->method('download')
                    ->with(
                        'https://youtube.com/watch?v=test',
                        $this->callback(static fn (array $options) => isset($options['extract-audio'])
                            && true === $options['extract-audio'])
                    )
                    ->willReturn($processResult);

        $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'best_format', false);

        $this->assertTrue(\method_exists($result, 'getVideos'));
    }

    /**
     * Verifies download returns a collection with a videos array.
     */
    public function testDownloadReturnsCollectionObject(): void
    {
        $processResult = $this->createMockProcessResult(true, '', '');

        /** @phpstan-ignore-next-line */
        $this->ytDlp->expects($this->once())
            ->method('download')
            ->willReturn($processResult);

        $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true);

        $this->assertTrue(\method_exists($result, 'getVideos'));
        $this->assertIsArray($result->getVideos());
    }

    /**
     * Verifies a successful process reports the downloaded file without error.
     */
    public function testDownloadHandlesSuccessfulProcessWithFile(): void
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'test_video_');
        $this->assertIsString($testFile);

        try {
            $stdout        = $testFile . "\n";
            $processResult = $this->createMockProcessResult(true, $stdout, '');

            /** @phpstan-ignore-next-line */
            $this->ytDlp->expects($this->once())
                ->method('download')
                ->willReturn($processResult);

            $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true);

            $videos = $result->getVideos();
            $this->assertCount(1, $videos);
            $this->assertNull($videos[0]->getError());
            $this->assertNotNull($videos[0]->getFile());
        } finally {
            @\unlink($testFile);
        }
    }

    /**
     * Verifies a failed process exposes the stderr message as the error.
     */
    public function testDownloadHandlesProcessFailureWithStderr(): void
    {
        $processResult = $this->createMockProcessResult(false, '', 'Download failed: video not found');

        /** @phpstan-ignore-next-line */
        $this->ytDlp->expects($this->once())
            ->method('download')
            ->willReturn($processResult);

        $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true);

        $videos = $result->getVideos();
        $this->assertCount(1, $videos);
        $this->assertNotNull($videos[0]->getError());
        $this->assertStringContainsString('Download failed', $videos[0]->getError());
    }

    /**
     * Verifies a failed process falls back to the stdout message as the error.
     */
    public function testDownloadHandlesProcessFailureWithStdout(): void
    {
        $processResult = $this->createMockProcessResult(false, 'Some error message', '');

        /** @phpstan-ignore-next-line */
        $this->ytDlp->expects($this->once())
            ->method('download')
            ->willReturn($processResult);

        $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true);

        $videos = $result->getVideos();
        $this->assertCount(1, $videos);
        $this->assertStringContainsString('Some error message', $videos[0]->getError());
    }

    /**
     * Verifies a failed process without output returns a generic error.
     */
    public function testDownloadHandlesProcessFailureWithoutOutput(): void
    {
        $processResult = $this->createMockProcessResult(false, '', '');

        /** @phpstan-ignore-next-line */
        $this->ytDlp->expects($this->once())
            ->method('download')
            ->willReturn($processResult);

        $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true);

        $videos = $result->getVideos();
        $this->assertCount(1, $videos);
        $this->assertSame('yt-dlp process failed.', $videos[0]->getError());
    }

    /**
     * Verifies a successful process without files reports a missing file error.
     */
    public function testDownloadHandlesSuccessfulProcessWithoutFiles(): void
    {
        $processResult = $this->createMockProcessResult(true, '', '');

        /** @phpstan-ignore-next-line */
        $this->ytDlp->expects($this->once())
            ->method('download')
            ->willReturn($processResult);

        $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true);

        $videos = $result->getVideos();
        $this->assertCount(1, $videos);
        $this->assertNotNull($videos[0]->getError());
        $this->assertStringContainsString('no downloaded file was reported', $videos[0]->getError());
    }

    /**
     * Verifies a successful video exposes file metadata to the caller.
     */
    public function testSuccessfulVideoExposesFileMetadata(): void
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'test_video_meta_');
        $this->assertIsString($testFile);
        \file_put_contents($testFile, 'video-content');

        try {
            $processResult = $this->createMockProcessResult(true, \sprintf("%s\n", $testFile), '');

            /** @phpstan-ignore-next-line */
            $this->ytDlp->expects($this->once())
                ->method('download')
                ->willReturn($processResult);

            $videos = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true)->getVideos();
            $file   = $videos[0]->getFile();

            $this->assertSame(\basename($testFile), $file->getBasename());
            $this->assertSame(\dirname($testFile), $file->getPath());
            $this->assertSame((float) \filesize($testFile), $file->getSize());
        } finally {
            @\unlink($testFile);
        }
    }

    /**
     * Verifies an errored video does not expose a file.
     */
    public function testErroredVideoHasNoFile(): void
    {
        $processResult = $this->createMockProcessResult(false, '', 'download failed');

        /** @phpstan-ignore-next-line */
        $this->ytDlp->expects($this->once())
            ->method('download')
            ->willReturn($processResult);

        $videos = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true)->getVideos();

        $this->assertNull($videos[0]->getFile());
    }

    /**
     * Verifies multiple reported files produce multiple video entries.
     */
    public function testDownloadHandlesMultipleFiles(): void
    {
        $testFile1 = \tempnam(\sys_get_temp_dir(), 'test_video_1_');
        $testFile2 = \tempnam(\sys_get_temp_dir(), 'test_video_2_');

        try {
            $stdout        = $testFile1 . "\n" . $testFile2 . "\n";
            $processResult = $this->createMockProcessResult(true, $stdout, '');

            /** @phpstan-ignore-next-line */
            $this->ytDlp->expects($this->once())
                ->method('download')
                ->willReturn($processResult);

            $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true);

            $videos = $result->getVideos();
            $this->assertCount(2, $videos);
            $this->assertNull($videos[0]->getError());
            $this->assertNull($videos[1]->getError());
        } finally {
            @\unlink($testFile1);
            @\unlink($testFile2);
        }
    }

    /**
     * Verifies repeated paths produce a single video entry.
     */
    public function testDownloadDeduplicatesPaths(): void
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'test_video_');

        try {
            $stdout        = $testFile . "\n" . $testFile . "\n";
            $processResult = $this->createMockProcessResult(true, $stdout, '');
            /** @phpstan-ignore-next-line */
            $this->ytDlp->expects($this->once())
                            ->method('download')
                            ->willReturn($processResult);

            $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true);

            $videos = $result->getVideos();
            $this->assertCount(1, $videos);
        } finally {
            @\unlink($testFile);
        }
    }

    /**
     * Verifies a reported path that does not exist reports an error.
     */
    public function testDownloadIgnoresNonExistentFiles(): void
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'test_video_');
        $this->assertIsString($testFile);
        @\unlink($testFile);

        $nonExistentFile = \sprintf('%s_nonexistent', $testFile);
        $stdout          = $nonExistentFile . "\n";
        $processResult   = $this->createMockProcessResult(true, $stdout, '');
        /** @phpstan-ignore-next-line */
        $this->ytDlp->expects($this->once())
                    ->method('download')
                    ->willReturn($processResult);

        $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true);

        $videos = $result->getVideos();
        $this->assertCount(1, $videos);
        $this->assertNotNull($videos[0]->getError());
    }

    /**
     * Verifies surrounding whitespace is stripped from reported paths.
     */
    public function testDownloadTrimsWhitespaceFromPaths(): void
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'test_video_');

        try {
            $stdout        = "  \n  " . $testFile . "  \n  ";
            $processResult = $this->createMockProcessResult(true, $stdout, '');
            /** @phpstan-ignore-next-line */
            $this->ytDlp->expects($this->once())
                            ->method('download')
                            ->willReturn($processResult);

            $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true);

            $videos = $result->getVideos();
            $this->assertCount(1, $videos);
        } finally {
            @\unlink($testFile);
        }
    }

    /**
     * Verifies a successful video exposes file name, path and size.
     */
    public function testSuccessfulVideoReturnsFileInfo(): void
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'test_video_');

        try {
            $stdout        = $testFile . "\n";
            $processResult = $this->createMockProcessResult(true, $stdout, '');

            /** @phpstan-ignore-next-line */
            $this->ytDlp->expects($this->once())
                ->method('download')
                ->willReturn($processResult);

            $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'format', true);

            $videos = $result->getVideos();
            $file   = $videos[0]->getFile();

            $this->assertNotNull($file);
            $this->assertTrue(\method_exists($file, 'getBasename'));
            $this->assertTrue(\method_exists($file, 'getPath'));
            $this->assertTrue(\method_exists($file, 'getSize'));
        } finally {
            @\unlink($testFile);
        }
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject&ProcessResult
     *
     * @phpstan-ignore-next-line
     */
    private function createMockProcessResult(bool $successful, string $stdout, string $stderr): mixed
    {
        /** @phpstan-ignore-next-line */
        $result = $this->createMock(ProcessResult::class);
        $result->expects($this->any())
            ->method('isSuccessful')
            ->willReturn($successful);
        $result->stdout = $stdout;
        $result->stderr = $stderr;

        return $result;
    }
}
