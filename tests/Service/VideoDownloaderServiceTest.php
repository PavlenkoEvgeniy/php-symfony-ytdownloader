<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadTask;
use App\Entity\Log;
use App\Entity\Source;
use App\Enum\DownloadFailureKind;
use App\Enum\DownloadOutcome;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Service\DownloadFailureClassifierInterface;
use App\Service\DownloadProgressNotifierInterface;
use App\Service\DownloadSizeEstimatorInterface;
use App\Service\FormatResolver;
use App\Service\LogManager;
use App\Service\ProgressReporterInterface;
use App\Service\SourceManager;
use App\Service\VideoDownloaderService;
use App\Service\YoutubeDlWrapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

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

        $formatResolver->expects($this->once())
            ->method('resolve')
            ->with('best')
            ->willReturn(['fmt', true]);

        $video      = $this->createSuccessfulVideo('video.mp4', '/tmp', 123.4);
        $collection = $this->createVideoCollection([$video]);
        $youtubeDl->expects($this->once())
            ->method('download')
            ->with('https://test.local', 'fmt', true, true, null, null)
            ->willReturn($collection);

        $sourceManager->expects($this->once())
            ->method('findByFilename')
            ->with('video.mp4')
            ->willReturn(null);
        $sourceManager->expects($this->once())
            ->method('createFromDownloadedFile')
            ->with('video.mp4', '/tmp', 123.4, null, null);
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

        $result = $this->service($youtubeDl, $formatResolver, $sourceManager, $logManager, $logger)
            ->process('https://test.local', 'best', '123')
        ;

        $this->assertSame(DownloadOutcome::SUCCESS, $result->outcome);
        $this->assertSame([
            ['commenced', 'Started downloading.', null],
            ['success', 'File "video.mp4" downloaded successfully.', 123.4],
            ['finished', 'Finished downloading.', null],
        ], $logCalls);
    }

    /**
     * Verifies a failed download is logged and returns its reason.
     */
    public function testProcessReturnsReasonWhenDownloadFails(): void
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

        $result = $this->service($youtubeDl, $formatResolver, $sourceManager, $logManager, $logger)
            ->process('https://test.local', 'best', '123')
        ;

        $this->assertSame(DownloadOutcome::ERROR, $result->outcome);
        $this->assertSame('download failed', $result->errorMessage);
        $this->assertSame(DownloadFailureKind::PERMANENT, $result->failureKind);
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

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $collection = $this->createVideoCollection([
            $this->createSuccessfulVideo('video.mp4', '/tmp', 100.0),
            $this->createErroredVideo('second item failed'),
        ]);
        $youtubeDl->expects($this->once())->method('download')->willReturn($collection);

        $sourceManager->expects($this->once())->method('findByFilename')->with('video.mp4')->willReturn(null);
        $sourceManager->expects($this->once())->method('createFromDownloadedFile')->with('video.mp4', '/tmp', 100.0, null, null);
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

        $result = $this->service($youtubeDl, $formatResolver, $sourceManager, $logManager, $logger)
            ->process('https://test.local', 'best', '123')
        ;

        $this->assertTrue($result->isSuccess());
        $this->assertSame([
            ['commenced', 'Started downloading.', null],
            ['success', 'File "video.mp4" downloaded successfully.', 100.0],
            ['error', 'Error during downloading: second item failed', null],
            ['finished', 'Finished downloading.', null],
        ], $logCalls);
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

        $result = $this->service($youtubeDl, $formatResolver, $sourceManager, $logManager, $logger)
            ->process('https://test.local', 'best')
        ;

        $this->assertSame(DownloadOutcome::ERROR, $result->outcome);
        $this->assertStringContainsString('no files were downloaded', (string) $result->errorMessage);
        $this->assertSame([
            ['commenced', 'Started downloading.', null],
            ['error', 'Error during downloading: no files were downloaded.', null],
        ], $logCalls);
    }

    /**
     * Verifies a Telegram download receives a progress callback backed by the notifier.
     */
    public function testTelegramDownloadReportsProgress(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createStub(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createStub(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createStub(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createStub(LogManager::class);
        $logger         = $this->createStub(LoggerInterface::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $task = new DownloadTask();
        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
            ->setTitle('My video');

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $reporter = $this->createMock(ProgressReporterInterface::class);
        $reporter->expects($this->once())->method('report')->with(50);

        $progressNotifier = $this->createMock(DownloadProgressNotifierInterface::class);
        $progressNotifier->expects($this->once())
            ->method('start')
            ->with(12_345, 'My video')
            ->willReturn($reporter);

        $youtubeDl->method('download')->willReturnCallback(
            function (string $url, string $format, bool $merge, bool $playlist, ?\Closure $onProgress): object {
                $onProgress?->__invoke(50);

                return $this->createVideoCollection([]);
            },
        );

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $taskRepository,
            $progressNotifier,
            $this->classifier(),
            $this->createStub(DownloadSizeEstimatorInterface::class),
            50,
        );

        $service->process('https://test.local', 'best', '12345', 7);
    }

    /**
     * Verifies an already downloaded file is handed to the current Telegram task.
     */
    public function testProcessHandsExistingFileToTelegramTask(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createStub(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createStub(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createMock(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createStub(LogManager::class);
        $logger         = $this->createStub(LoggerInterface::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $existing = new Source();

        $existing->setFilename('video.mp4')
            ->setFilepath('/tmp')
        ;

        $sourceManager->expects($this->once())
            ->method('findByFilename')
            ->with('video.mp4')
            ->willReturn($existing);
        $sourceManager->expects($this->never())->method('createFromDownloadedFile');
        $sourceManager->expects($this->once())->method('flush');

        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
        ;

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $youtubeDl->method('download')->willReturn(
            $this->createVideoCollection([$this->createSuccessfulVideo('video.mp4', '/tmp', 123.4)]),
        );

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $taskRepository,
            $this->createStub(DownloadProgressNotifierInterface::class),
            $this->classifier(),
            $this->createStub(DownloadSizeEstimatorInterface::class),
            50,
        );

        $result = $service->process('https://test.local', 'best', '12345', 7);

        $this->assertTrue($result->isSuccess());
        $this->assertSame($task, $existing->getDownloadTask());
        $this->assertSame(DownloadTask::ORIGIN_TELEGRAM, $existing->getOrigin());
    }

    /**
     * Verifies no progress reporter is created without any chat to report to.
     */
    public function testProcessSkipsProgressWithoutChat(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createStub(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createStub(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createStub(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createStub(LogManager::class);
        $logger         = $this->createStub(LoggerInterface::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);
        $youtubeDl->method('download')->willReturn($this->createVideoCollection([]));

        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
        ;

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $progressNotifier = $this->createMock(DownloadProgressNotifierInterface::class);
        $progressNotifier->expects($this->never())->method('start');

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $taskRepository,
            $progressNotifier,
            $this->classifier(),
            $this->createStub(DownloadSizeEstimatorInterface::class),
            50,
        );

        $service->process('https://test.local', 'best', '', 7);
    }

    /**
     * Verifies an over-limit Telegram download is rejected before any transfer.
     */
    public function testProcessRejectsOversizedTelegramDownloadBeforeTransfer(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createMock(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createStub(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createStub(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createMock(LogManager::class);
        $logger         = $this->createMock(LoggerInterface::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $youtubeDl->expects($this->never())->method('download');
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
            ->method('info')
            ->with('Rejected by the Telegram size guard', $this->arrayHasKey('reason'));

        $estimator = $this->createStub(DownloadSizeEstimatorInterface::class);
        $estimator->method('estimate')->willReturn([60 * 1024 * 1024]);

        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
        ;

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $progressNotifier = $this->createMock(DownloadProgressNotifierInterface::class);
        $progressNotifier->expects($this->never())->method('start');

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $taskRepository,
            $progressNotifier,
            $this->classifier(),
            $estimator,
            50,
        );

        $result = $service->process('https://test.local', 'best', '12345', 7);

        $this->assertTrue($result->isSizeExceeded());
        $this->assertFalse($result->isTransient());
        $this->assertSame(DownloadOutcome::ERROR, $result->outcome);
        $this->assertStringContainsString('Telegram upload limit', (string) $result->errorMessage);
        $this->assertSame([
            ['commenced', 'Started downloading.', null],
            ['error', 'Estimated file size 60 MB exceeds the Telegram upload limit of 50 MB. Choose another quality.', null],
        ], $logCalls);
    }

    /**
     * Verifies a Telegram download within the configured limit proceeds as usual.
     */
    public function testProcessDownloadsWhenEstimateIsWithinLimit(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createMock(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createStub(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createStub(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createStub(LogManager::class);
        $logger         = $this->createStub(LoggerInterface::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $youtubeDl->expects($this->once())
            ->method('download')
            ->with('https://test.local', 'fmt', true, false, null, '100M')
            ->willReturn($this->createVideoCollection([$this->createSuccessfulVideo('video.mp4', '/tmp', 1.0)]));

        $estimator = $this->createMock(DownloadSizeEstimatorInterface::class);
        $estimator->expects($this->once())->method('estimate')->willReturn([100 * 1024 * 1024]);

        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
        ;

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $taskRepository,
            $this->createStub(DownloadProgressNotifierInterface::class),
            $this->classifier(),
            $estimator,
            100,
        );

        $result = $service->process('https://test.local', 'best', '', 7);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isSizeExceeded());
    }

    /**
     * Verifies a Telegram download proceeds when no size can be estimated.
     */
    public function testProcessDownloadsWhenSizeCannotBeEstimated(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createMock(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createStub(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createStub(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createStub(LogManager::class);
        $logger         = $this->createStub(LoggerInterface::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $youtubeDl->expects($this->once())
            ->method('download')
            ->willReturn($this->createVideoCollection([$this->createSuccessfulVideo('video.mp4', '/tmp', 1.0)]));

        $estimator = $this->createMock(DownloadSizeEstimatorInterface::class);
        $estimator->expects($this->once())->method('estimate')->willReturn([]);

        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
        ;

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $taskRepository,
            $this->createStub(DownloadProgressNotifierInterface::class),
            $this->classifier(),
            $estimator,
            50,
        );

        $result = $service->process('https://test.local', 'best', '', 7);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Verifies web and API downloads are never subject to the size guard.
     */
    public function testProcessIgnoresSizeGuardForNonTelegramTasks(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createMock(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createStub(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createStub(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createStub(LogManager::class);
        $logger         = $this->createStub(LoggerInterface::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $youtubeDl->expects($this->once())
            ->method('download')
            ->with('https://test.local', 'fmt', true, true, null, null)
            ->willReturn($this->createVideoCollection([$this->createSuccessfulVideo('video.mp4', '/tmp', 1.0)]));

        $estimator = $this->createMock(DownloadSizeEstimatorInterface::class);
        $estimator->expects($this->never())->method('estimate');

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $this->createStub(DownloadTaskRepositoryInterface::class),
            $this->createStub(DownloadProgressNotifierInterface::class),
            $this->classifier(),
            $estimator,
            50,
        );

        $result = $service->process('https://test.local', 'best');

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Verifies a transfer-cap refusal from the downloader becomes the size rejection.
     */
    public function testProcessMapsTransferCapRefusalToSizeRejection(): void
    {
        /** @phpstan-ignore-next-line */
        $youtubeDl      = $this->createMock(YoutubeDlWrapper::class);
        /** @phpstan-ignore-next-line */
        $formatResolver = $this->createStub(FormatResolver::class);
        /** @phpstan-ignore-next-line */
        $sourceManager  = $this->createStub(SourceManager::class);
        /** @phpstan-ignore-next-line */
        $logManager     = $this->createMock(LogManager::class);
        $logger         = $this->createMock(LoggerInterface::class);

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $refusalLine = '[download] File is larger than max-filesize (2889135013 bytes > 52428800 bytes). Aborting.';

        $youtubeDl->expects($this->once())
            ->method('download')
            ->with('https://test.local', 'fmt', true, false, null, '50M')
            ->willReturn($this->createVideoCollection([$this->createErroredVideo($refusalLine)]));

        $estimator = $this->createMock(DownloadSizeEstimatorInterface::class);
        $estimator->expects($this->once())->method('estimate')->willReturn([]);

        $logCalls = [];
        $logManager->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (string $type, string $message, ?float $size = null) use (&$logCalls) {
                $logCalls[] = [$type, $message, $size];

                return new Log();
            });
        $logManager->expects($this->exactly(2))->method('flush');

        $logger->expects($this->once())
            ->method('info')
            ->with('Rejected by the Telegram size guard', $this->arrayHasKey('reason'));

        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
        ;

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $taskRepository,
            $this->createStub(DownloadProgressNotifierInterface::class),
            $this->classifier(),
            $estimator,
            50,
        );

        $result = $service->process('https://test.local', 'best', '', 7);

        $this->assertTrue($result->isSizeExceeded());
        $this->assertFalse($result->isTransient());
        $this->assertSame(DownloadOutcome::ERROR, $result->outcome);
        $this->assertSame(
            'File size 2755 MB exceeds the Telegram upload limit of 50 MB. Choose another quality.',
            $result->errorMessage,
        );
        $this->assertSame([
            ['commenced', 'Started downloading.', null],
            ['error', \sprintf('Error during downloading: %s', $refusalLine), null],
        ], $logCalls);
    }

    /**
     * Verifies a completed file past the limit is removed instead of kept.
     */
    public function testProcessRemovesCompletedOversizedTelegramFile(): void
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

        $formatResolver->method('resolve')->willReturn(['fmt', true]);

        $completedFile = \tempnam(\sys_get_temp_dir(), 'oversize_');

        if (false === $completedFile) {
            $this->fail('Cannot create a temporary file.');
        }

        \file_put_contents($completedFile, 'x');

        $youtubeDl->expects($this->once())
            ->method('download')
            ->willReturn($this->createVideoCollection([
                $this->createSuccessfulVideo(\basename($completedFile), \dirname($completedFile), 60.0 * 1024 * 1024),
            ]));

        $estimator = $this->createMock(DownloadSizeEstimatorInterface::class);
        $estimator->expects($this->once())->method('estimate')->willReturn([]);

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
            ->method('info')
            ->with('Rejected by the Telegram size guard', $this->arrayHasKey('reason'));

        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
        ;

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $service = new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $taskRepository,
            $this->createStub(DownloadProgressNotifierInterface::class),
            $this->classifier(),
            $estimator,
            50,
        );

        $result = $service->process('https://test.local', 'best', '', 7);

        $this->assertTrue($result->isSizeExceeded());
        $this->assertFalse($result->isTransient());
        $this->assertSame(
            'File size 60 MB exceeds the Telegram upload limit of 50 MB. Choose another quality.',
            $result->errorMessage,
        );
        $this->assertFileDoesNotExist($completedFile);
        $this->assertSame([
            ['commenced', 'Started downloading.', null],
            ['error', 'File size 60 MB exceeds the Telegram upload limit of 50 MB. Choose another quality.', null],
        ], $logCalls);

        @\unlink($completedFile);
    }

    private function service(
        YoutubeDlWrapper $youtubeDl,
        FormatResolver $formatResolver,
        SourceManager $sourceManager,
        LogManager $logManager,
        LoggerInterface $logger,
    ): VideoDownloaderService {
        return new VideoDownloaderService(
            $youtubeDl,
            $formatResolver,
            $sourceManager,
            $logManager,
            $logger,
            $this->createStub(DownloadTaskRepositoryInterface::class),
            $this->createStub(DownloadProgressNotifierInterface::class),
            $this->classifier(),
            $this->createStub(DownloadSizeEstimatorInterface::class),
            50,
        );
    }

    private function classifier(): DownloadFailureClassifierInterface
    {
        $classifier = $this->createStub(DownloadFailureClassifierInterface::class);
        $classifier->method('classify')->willReturn(DownloadFailureKind::PERMANENT);

        return $classifier;
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
}
