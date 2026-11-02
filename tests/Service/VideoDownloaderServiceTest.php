<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Log;
use App\Enum\DownloadOutcome;
use App\Service\FormatResolver;
use App\Service\LogManager;
use App\Service\SourceManager;
use App\Service\TelegramNotifier;
use App\Service\VideoDownloaderService;
use App\Service\YoutubeDlWrapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class VideoDownloaderServiceTest extends TestCase
{
    /**
     * Verifies a successful download is stored, logged and reported.
     */
    public function testProcessLogsAndStoresSuccessfulDownload(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createMock(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createMock(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createMock(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createMock(LogManager::class);
        $logger         = $this->createMock(LoggerInterface::class);
        /** @phpstan-ignore-next-line */
        $notifier       = $this->createMock(TelegramNotifier::class);

        $formatResolver->expects($this->once())
            ->method('resolve')
            ->with('best')
            ->willReturn(['fmt', true]);

        $video       = $this->createSuccessfulVideo('video.mp4', '/tmp', 123.4);
        $collection  = $this->createVideoCollection([$video]);
        $youtubeDl->expects($this->once())
            ->method('download')
            ->with('https://test.local', 'fmt', true)
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
            $this->createTranslator(),
        );

        $outcome = $service->process('https://test.local', 'best', '123');

        $this->assertSame(DownloadOutcome::SUCCESS, $outcome);
        $this->assertSame([
            ['commenced', 'Started downloading.', null],
            ['success', 'File "video.mp4" downloaded successfully.', 123.4],
            ['finished', 'Finished downloading.', null],
        ], $logCalls);
    }

    /**
     * Verifies a failed download logs the error and notifies the user.
     */
    public function testProcessNotifiesErrorAndStopsWhenDownloadFails(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createMock(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createStub(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createMock(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createMock(LogManager::class);
        $logger         = $this->createMock(LoggerInterface::class);
        /** @phpstan-ignore-next-line */
        $notifier       = $this->createMock(TelegramNotifier::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $video      = $this->createErroredVideo('download failed');
        $collection = $this->createVideoCollection([$video]);
        $youtubeDl->expects($this->once())
            ->method('download')
            ->willReturn($collection);

        $sourceManager->expects($this->never())->method('findByFilename');
        $sourceManager->expects($this->never())->method('createFromDownloadedFile');
        $sourceManager->expects($this->once())->method('flush');

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
            $this->createTranslator(),
        );

        $outcome = $service->process('https://test.local', 'best', '123');

        $this->assertSame(DownloadOutcome::ERROR, $outcome);
        $this->assertSame([
            ['commenced', 'Started downloading.', null],
            ['error', 'Error during downloading: download failed', null],
        ], $logCalls);
    }

    /**
     * Verifies a partial playlist keeps successful items and still reports success.
     */
    public function testProcessKeepsPartialPlaylistAsSuccessAndFlushesSources(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createMock(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createStub(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createMock(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createMock(LogManager::class);
        $logger         = $this->createMock(LoggerInterface::class);
        /** @phpstan-ignore-next-line */
        $notifier       = $this->createMock(TelegramNotifier::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $collection = $this->createVideoCollection([
            $this->createSuccessfulVideo('video.mp4', '/tmp', 100.0),
            $this->createErroredVideo('second item failed'),
        ]);
        $youtubeDl->expects($this->once())->method('download')->willReturn($collection);

        $sourceManager->expects($this->once())->method('findByFilename')->with('video.mp4')->willReturn(null);
        $sourceManager->expects($this->once())->method('createFromDownloadedFile')->with('video.mp4', '/tmp', 100.0);
        $sourceManager->expects($this->once())->method('flush');

        $logCalls = [];
        $logManager->expects($this->exactly(4))
            ->method('create')
            ->willReturnCallback(function (string $type, string $message, ?float $size = null) use (&$logCalls) {
                $logCalls[] = [$type, $message, $size];

                return new Log();
            });
        $logManager->expects($this->exactly(3))->method('flush');

        $logger->expects($this->once())->method('error');
        $logger->expects($this->once())->method('info');

        $notifier->expects($this->once())->method('notifyFinished')->with('123', $this->stringContains('Downloading finished'));
        $notifier->expects($this->never())->method('notifyError');

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $notifier,
            $this->createTranslator(),
        );

        $outcome = $service->process('https://test.local', 'best', '123');

        $this->assertSame(DownloadOutcome::SUCCESS, $outcome);
        $this->assertSame([
            ['commenced', 'Started downloading.', null],
            ['success', 'File "video.mp4" downloaded successfully.', 100.0],
            ['error', 'Error during downloading: second item failed', null],
            ['finished', 'Finished downloading.', null],
        ], $logCalls);
    }

    /**
     * Verifies a failed download returns an error without notifying when no user id is given.
     */
    public function testProcessReturnsErrorWithoutNotifyingWhenNoTelegramUser(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createMock(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createStub(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createMock(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createMock(LogManager::class);
        $logger         = $this->createMock(LoggerInterface::class);
        /** @phpstan-ignore-next-line */
        $notifier       = $this->createMock(TelegramNotifier::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $collection = $this->createVideoCollection([$this->createErroredVideo('download failed')]);
        $youtubeDl->expects($this->once())->method('download')->willReturn($collection);

        $sourceManager->expects($this->never())->method('findByFilename');
        $sourceManager->expects($this->never())->method('createFromDownloadedFile');
        $sourceManager->expects($this->once())->method('flush');

        $logManager->expects($this->exactly(2))->method('create');
        $logManager->expects($this->exactly(2))->method('flush');

        $logger->expects($this->once())->method('error');

        $notifier->expects($this->never())->method('notifyFinished');
        $notifier->expects($this->never())->method('notifyError');

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $notifier,
            $this->createTranslator(),
        );

        $this->assertSame(DownloadOutcome::ERROR, $service->process('https://test.local', 'best'));
    }

    /**
     * Verifies an empty download result is logged as an error.
     */
    public function testProcessLogsErrorWhenNoVideosWereReturned(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createMock(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createStub(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createMock(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createMock(LogManager::class);
        $logger         = $this->createMock(LoggerInterface::class);
        /** @phpstan-ignore-next-line */
        $notifier       = $this->createMock(TelegramNotifier::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);
        $youtubeDl->method('download')->willReturn($this->createVideoCollection([]));

        $sourceManager->expects($this->once())->method('flush');
        $sourceManager->expects($this->never())->method('findByFilename');
        $sourceManager->expects($this->never())->method('createFromDownloadedFile');

        $logCalls = [];
        $logManager->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (string $type, string $message, ?float $size = null) use (&$logCalls) {
                $logCalls[] = [$type, $message, $size];

                return new Log();
            });
        $logManager->expects($this->exactly(2))->method('flush');

        $logger->expects($this->never())->method('error');
        $notifier->expects($this->never())->method('notifyFinished');
        $notifier->expects($this->never())->method('notifyError');

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $notifier,
            $this->createTranslator(),
        );

        $outcome = $service->process('https://test.local', 'best');

        $this->assertSame(DownloadOutcome::ERROR, $outcome);
        $this->assertSame([
            ['commenced', 'Started downloading.', null],
            ['error', 'Error during downloading: no files were downloaded.', null],
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

            /** @phpstan-ignore-next-line */
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

            public function getError(): string
            {
                return $this->error;
            }

            /** @return null */
            public function getFile()
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

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => match ($id) {
            'telegram.download_finished' => 'Downloading finished. You can find your file in the downloads directory. To download please visit the website.',
            'telegram.download_error'    => 'Error during downloading: please try again with another link.',
            default                      => $id,
        });

        return $translator;
    }
}
