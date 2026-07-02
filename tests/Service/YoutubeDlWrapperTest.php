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

    public function testDownloadVideoCallsYtDlpWithVideoOptions(): void
    {
        $processResult = $this->createMockProcessResult(true, '', '');

        /** @phpstan-ignore-next-line */
        $this->ytDlp->expects($this->once())
            ->method('download')
            ->with(
                'https://youtube.com/watch?v=test',
                $this->callback(function (array $options) {
                    return isset($options['merge-output-format'])
                        && $options['merge-output-format'] === VideoDownloaderOption::MERGE_OUTPUT_FORMAT_VIDEO->value;
                })
            )
            ->willReturn($processResult);

        $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'best_format', true);

        $this->assertTrue(\method_exists($result, 'getVideos'));
    }

    public function testDownloadAudioCallsYtDlpWithAudioOptions(): void
    {
        $processResult = $this->createMockProcessResult(true, '', '');
        /** @phpstan-ignore-next-line */
        $this->ytDlp->expects($this->once())
                    ->method('download')
                    ->with(
                        'https://youtube.com/watch?v=test',
                        $this->callback(function (array $options) {
                            return isset($options['extract-audio'])
                                && true === $options['extract-audio'];
                        })
                    )
                    ->willReturn($processResult);

        $result = $this->wrapper->download('https://youtube.com/watch?v=test', 'best_format', false);

        $this->assertTrue(\method_exists($result, 'getVideos'));
    }

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

    public function testDownloadHandlesSuccessfulProcessWithFile(): void
    {
        // Create a temporary test file
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

    public function testDownloadIgnoresNonExistentFiles(): void
    {
        $testFile = \tempnam(\sys_get_temp_dir(), 'test_video_');
        $this->assertIsString($testFile);
        @\unlink($testFile);

        $nonExistentFile = $testFile . '_nonexistent';
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
