<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Log;
use App\Service\FormatResolver;
use App\Service\LogManager;
use App\Service\SourceManager;
use App\Service\TelegramNotifier;
use App\Service\VideoDownloaderService;
use App\Service\YoutubeDlWrapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class VideoDownloaderServiceTest extends TestCase
{
    public function testProcessLogsAndStoresSuccessfulDownload(): void
    {
        $youtubeDl      = $this->createMock(YoutubeDlWrapper::class);
        $formatResolver = $this->createMock(FormatResolver::class);
        $sourceManager  = $this->createMock(SourceManager::class);
        $logManager     = $this->createMock(LogManager::class);
        $logger         = $this->createMock(LoggerInterface::class);
        $notifier       = $this->createMock(TelegramNotifier::class);

        $formatResolver->expects($this->once())
            ->method('resolve')
            ->with('best')
            ->willReturn(['fmt', true]);

        $video       = $this->createSuccessfulVideo('video.mp4', '/tmp', 123.4);
        $collection  = $this->createVideoCollection([$video]);
        $youtubeDl->expects($this->once())
            ->method('download')
            ->with('https://example.com', 'fmt', true)
            ->willReturn($collection);

        $sourceManager->expects($this->once())
            ->method('findByFilename')
            ->with('video.mp4')
            ->willReturn(null);
        $sourceManager->expects($this->once())
            ->method('createFromDownloadedFile')
            ->with('video.mp4', '/tmp', 123.4);
        $sourceManager->expects($this->once())
            ->method('flush');

        $logCalls = [];
        $logManager->expects($this->exactly(3))
            ->method('create')
            ->willReturnCallback(function (string $type, string $message, ?float $size = null) use (&$logCalls) {
                $logCalls[] = [$type, $message, $size];

                return new Log();
            });
        $logManager->expects($this->exactly(2))->method('flush');

        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('video.mp4'));
        $logger->expects($this->never())->method('error');

        $notifier->expects($this->once())
            ->method('notifyFinished')
            ->with('123', $this->stringContains('Downloading finished'));
        $notifier->expects($this->never())->method('notifyError');

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $notifier,
        );

        $service->process('https://example.com', 'best', '123');

        $this->assertSame([
            ['commenced', 'Started downloading.', null],
            ['success', 'File "video.mp4" downloaded successfully.', 123.4],
            ['finished', 'Finished downloading.', null],
        ], $logCalls);
    }

    public function testProcessNotifiesErrorAndStopsWhenDownloadFails(): void
    {
        $youtubeDl      = $this->createMock(YoutubeDlWrapper::class);
        $formatResolver = $this->createMock(FormatResolver::class);
        $sourceManager  = $this->createMock(SourceManager::class);
        $logManager     = $this->createMock(LogManager::class);
        $logger         = $this->createMock(LoggerInterface::class);
        $notifier       = $this->createMock(TelegramNotifier::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $video      = $this->createErroredVideo('download failed');
        $collection = $this->createVideoCollection([$video]);
        $youtubeDl->expects($this->once())
            ->method('download')
            ->willReturn($collection);

        $sourceManager->expects($this->never())->method('findByFilename');
        $sourceManager->expects($this->never())->method('createFromDownloadedFile');
        $sourceManager->expects($this->never())->method('flush');

        $logCalls = [];
        $logManager->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (string $type, string $message, ?float $size = null) use (&$logCalls) {
                $logCalls[] = [$type, $message, $size];

                return new Log();
            });
        $logManager->expects($this->exactly(2))->method('flush');

        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error during downloading'));
        $logger->expects($this->never())->method('info');

        $notifier->expects($this->once())
            ->method('notifyError')
            ->with('123', $this->stringContains('please try again'));
        $notifier->expects($this->never())->method('notifyFinished');

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $notifier,
        );

        $service->process('https://example.com', 'best', '123');

        $this->assertSame([
            ['commenced', 'Started downloading.', null],
            ['error', 'Error during downloading: download failed', null],
        ], $logCalls);
    }

    private function createSuccessfulVideo(string $basename, string $path, float $size): object
    {
        $file = new class($basename, $path, $size) {
            public function __construct(private string $basename, private string $path, private float $size)
            {
            }

            public function getBasename(): string
            {
                return $this->basename;
            }

            public function getPath(): string
            {
                return $this->path;
            }

            public function getSize(): float
            {
                return $this->size;
            }
        };

        return new class($file) {
            public function __construct(private object $file)
            {
            }

            public function getError(): ?string
            {
                return null;
            }

            public function getFile(): object
            {
                return $this->file;
            }
        };
    }

    private function createErroredVideo(string $error): object
    {
        return new class($error) {
            public function __construct(private string $error)
            {
            }

            public function getError(): ?string
            {
                return $this->error;
            }

            public function getFile(): ?object
            {
                return null;
            }
        };
    }

    /**
     * @param array<int, object> $videos
     */
    private function createVideoCollection(array $videos): object
    {
        return new class($videos) {
            /**
             * @param array<int, object> $videos
             */
            public function __construct(private array $videos)
            {
            }

            /**
             * @return array<int, object>
             */
            public function getVideos(): array
            {
                return $this->videos;
            }
        };
    }
}
