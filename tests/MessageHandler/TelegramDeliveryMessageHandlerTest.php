<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\DownloadTask;
use App\Entity\Source;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use App\Message\TelegramDeliveryMessage;
use App\MessageHandler\TelegramDeliveryMessageHandler;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Repository\SourceRepositoryInterface;
use App\Service\AuditLoggerInterface;
use App\Service\DownloadTaskManagerInterface;
use App\Service\TelegramClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class TelegramDeliveryMessageHandlerTest extends TestCase
{
    private ?string $directory = null;

    protected function tearDown(): void
    {
        if (null === $this->directory || !\is_dir($this->directory)) {
            return;
        }

        @\chmod($this->directory, 0755);

        \array_map('unlink', \glob(\sprintf('%s/*', $this->directory)) ?: []);
        \rmdir($this->directory);
    }

    /**
     * Verifies delivery and Source deletion are audited under the user's email.
     */
    public function testAuditsDeliveryAndSourceDeletionUnderUserEmail(): void
    {
        $this->directory = \sprintf(
            '%s/ytdownloader-audit-%s',
            \sys_get_temp_dir(),
            \bin2hex(\random_bytes(6)),
        );
        \mkdir($this->directory);
        $path = \sprintf('%s/video.mp4', $this->directory);
        \file_put_contents($path, 'video');

        $user = new User()->setEmail('telegram@test.local')->setTelegramUserId(12_345);

        $task = new DownloadTask();

        $task->setUrl('https://test.local/video')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
            ->setUser($user)
            ->setDeliveryStatus(DownloadTask::DELIVERY_PENDING);
        (new \ReflectionProperty(DownloadTask::class, 'id'))->setValue($task, 7);

        $source = new Source();
        $source->setFilepath($this->directory)->setFilename('video.mp4')->setDownloadTask($task);

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $sourceRepository = $this->createStub(SourceRepositoryInterface::class);
        $sourceRepository->method('findByDownloadTask')->willReturn([$source]);

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendVideo')
            ->with(12_345, $path, 'video.mp4 · best')
            ->willReturn(99);

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_SENT, 99);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($source);
        $entityManager->expects($this->once())->method('flush');

        $calls       = [];
        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->exactly(2))
            ->method('log')
            ->willReturnCallback(static function (AuditAction $action, mixed ...$arguments) use (&$calls): void {
                $calls[] = [$action, ...$arguments];
            });

        $handler = new TelegramDeliveryMessageHandler(
            $taskRepository,
            $sourceRepository,
            $telegramClient,
            $taskManager,
            $entityManager,
            $auditLogger,
            $this->createStub(LoggerInterface::class),
            50,
            'https://test.local',
        );

        $handler->__invoke(new TelegramDeliveryMessage(7));

        $this->assertCount(2, $calls);

        $deletion = $calls[0];
        $this->assertSame(AuditAction::SOURCE_DELETE, $deletion[0]);
        $this->assertSame(AuditChannel::TELEGRAM, $deletion[1]);
        $this->assertSame(['reason' => 'telegram_delivery', 'telegram_chat_id' => 12_345], $deletion[6]);
        $this->assertNull($deletion[7]);
        $this->assertNull($deletion[8]);
        $this->assertSame($user, $deletion[9]);

        $delivery = $calls[1];
        $this->assertSame(AuditAction::TELEGRAM_DELIVERED, $delivery[0]);
        $this->assertSame(AuditChannel::TELEGRAM, $delivery[1]);
        $this->assertSame(['sent' => 1, 'failed' => 0, 'telegram_chat_id' => 12_345], $delivery[6]);
        $this->assertNull($delivery[7]);
        $this->assertNull($delivery[8]);
        $this->assertSame($user, $delivery[9]);
    }

    /**
     * Verifies a task from another origin is left untouched.
     */
    public function testTaskFromAnotherOriginIsSkipped(): void
    {
        $task = $this->telegramTask();
        $task->setOrigin(DownloadTask::ORIGIN_UI);

        $sourceRepository = $this->createMock(SourceRepositoryInterface::class);
        $sourceRepository->expects($this->never())->method('findByDownloadTask');

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->never())->method('markDeliveryStatus');

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->never())->method('sendMessage');

        $this->handler($task, $sourceRepository, $telegramClient, $taskManager, $this->createStub(LoggerInterface::class))
            ->__invoke(new TelegramDeliveryMessage(7));
    }

    /**
     * Verifies an already delivered task is not sent again.
     */
    public function testAlreadySentDeliveryIsSkipped(): void
    {
        $task = $this->telegramTask(deliveryStatus: DownloadTask::DELIVERY_SENT);

        $sourceRepository = $this->createMock(SourceRepositoryInterface::class);
        $sourceRepository->expects($this->never())->method('findByDownloadTask');

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->never())->method('markDeliveryStatus');

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->never())->method('sendMessage');

        $this->handler($task, $sourceRepository, $telegramClient, $taskManager, $this->createStub(LoggerInterface::class))
            ->__invoke(new TelegramDeliveryMessage(7));
    }

    /**
     * Verifies a delivery for an unlinked account is marked as skipped.
     */
    public function testDeliveryIsSkippedWhenTheAccountLostItsLink(): void
    {
        $task = $this->telegramTask(linked: false);

        $sourceRepository = $this->createStub(SourceRepositoryInterface::class);

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_SKIPPED);

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->never())->method('sendMessage');

        $this->handler($task, $sourceRepository, $telegramClient, $taskManager, $this->createStub(LoggerInterface::class))
            ->__invoke(new TelegramDeliveryMessage(7));
    }

    /**
     * Verifies a task without a chat id is marked as skipped.
     */
    public function testDeliveryIsSkippedWithoutAChatId(): void
    {
        $task = $this->telegramTask();
        $task->setTelegramChatId(null);

        $sourceRepository = $this->createStub(SourceRepositoryInterface::class);

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_SKIPPED);

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->never())->method('sendMessage');

        $this->handler($task, $sourceRepository, $telegramClient, $taskManager, $this->createStub(LoggerInterface::class))
            ->__invoke(new TelegramDeliveryMessage(7));
    }

    /**
     * Verifies a finished download without any file tells the user where to look.
     */
    public function testDeliveryWithoutFilesFailsAndPointsToTheLibrary(): void
    {
        $task = $this->telegramTask();

        $sourceRepository = $this->createStub(SourceRepositoryInterface::class);
        $sourceRepository->method('findByDownloadTask')->willReturn([]);

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_FAILED);

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendMessage')
            ->with(12_345, $this->stringContains('no file was produced'))
            ->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('remove');

        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->never())->method('log');

        $this->handler(
            $task,
            $sourceRepository,
            $telegramClient,
            $taskManager,
            $this->createStub(LoggerInterface::class),
            $auditLogger,
            $entityManager,
        )->__invoke(new TelegramDeliveryMessage(7));
    }

    /**
     * Verifies a vanished file fails the delivery instead of crashing it.
     */
    public function testMissingFileFailsTheDelivery(): void
    {
        $directory = $this->createDirectory();
        $task      = $this->telegramTask();
        $source    = $this->source($task, $directory, 'vanished.mp4');

        $sourceRepository = $this->createStub(SourceRepositoryInterface::class);
        $sourceRepository->method('findByDownloadTask')->willReturn([$source]);

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_FAILED);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Telegram delivery file is missing', $this->arrayHasKey('path'));

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendMessage')
            ->with(12_345, $this->stringContains('could not send the file'))
            ->willReturn(1);

        $auditCalls  = [];
        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->willReturnCallback(static function (AuditAction $action, mixed ...$arguments) use (&$auditCalls): void {
                $auditCalls[] = [$action, ...$arguments];
            });

        $this->handler(
            $task,
            $sourceRepository,
            $telegramClient,
            $taskManager,
            $logger,
            $auditLogger,
        )->__invoke(new TelegramDeliveryMessage(7));

        $this->assertSame(AuditAction::TELEGRAM_DELIVERY_FAILED, $auditCalls[0][0]);
        $this->assertSame(['sent' => 0, 'failed' => 1, 'telegram_chat_id' => 12_345], $auditCalls[0][6]);
    }

    /**
     * Verifies a file above the bot upload limit is not uploaded.
     */
    public function testFileAboveSizeLimitIsNotUploaded(): void
    {
        $directory = $this->createDirectory();
        $task      = $this->telegramTask();
        $source    = $this->source($task, $directory, 'huge.mp4', size: 10.0);
        \file_put_contents(\sprintf('%s/huge.mp4', $directory), 'video');

        $sourceRepository = $this->createStub(SourceRepositoryInterface::class);
        $sourceRepository->method('findByDownloadTask')->willReturn([$source]);

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_FAILED);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Telegram delivery file is too large', $this->arrayHasKey('limit'));

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->never())->method('sendVideo');

        $this->handler(
            $task,
            $sourceRepository,
            $telegramClient,
            $taskManager,
            $logger,
            maxUploadMb: 0,
        )->__invoke(new TelegramDeliveryMessage(7));
    }

    /**
     * Verifies an audio file is sent through the audio method with the task title.
     */
    public function testAudioFileIsSentAsAudioWithTheTaskTitle(): void
    {
        $directory = $this->createDirectory();
        $task      = $this->telegramTask(title: 'My Video');
        $source    = $this->source($task, $directory, 'track.mp3');
        \file_put_contents(\sprintf('%s/track.mp3', $directory), 'audio');

        $sourceRepository = $this->createStub(SourceRepositoryInterface::class);
        $sourceRepository->method('findByDownloadTask')->willReturn([$source]);

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_SENT, 55);

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendAudio')
            ->with(12_345, \sprintf('%s/track.mp3', $directory), 'My Video · best')
            ->willReturn(55);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($source);

        $this->handler(
            $task,
            $sourceRepository,
            $telegramClient,
            $taskManager,
            $this->createStub(LoggerInterface::class),
            entityManager: $entityManager,
        )->__invoke(new TelegramDeliveryMessage(7));

        $this->assertFileDoesNotExist(\sprintf('%s/track.mp3', $directory));
    }

    /**
     * Verifies an unknown extension is sent as a document.
     */
    public function testUnknownExtensionIsSentAsADocument(): void
    {
        $directory = $this->createDirectory();
        $task      = $this->telegramTask();
        $source    = $this->source($task, $directory, 'notes.txt');
        \file_put_contents(\sprintf('%s/notes.txt', $directory), 'notes');

        $sourceRepository = $this->createStub(SourceRepositoryInterface::class);
        $sourceRepository->method('findByDownloadTask')->willReturn([$source]);

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_SENT, 56);

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendDocument')
            ->with(12_345, \sprintf('%s/notes.txt', $directory), 'notes.txt · best')
            ->willReturn(56);
        $telegramClient->expects($this->never())->method('sendVideo');

        $this->handler(
            $task,
            $sourceRepository,
            $telegramClient,
            $taskManager,
            $this->createStub(LoggerInterface::class),
        )->__invoke(new TelegramDeliveryMessage(7));
    }

    /**
     * Verifies a codec rejected by the video method falls back to a document upload.
     */
    public function testVideoFallsBackToADocumentUpload(): void
    {
        $directory = $this->createDirectory();
        $task      = $this->telegramTask();
        $source    = $this->source($task, $directory, 'clip.mkv');
        \file_put_contents(\sprintf('%s/clip.mkv', $directory), 'video');

        $sourceRepository = $this->createStub(SourceRepositoryInterface::class);
        $sourceRepository->method('findByDownloadTask')->willReturn([$source]);

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_SENT, 57);

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendVideo')
            ->willThrowException(new \RuntimeException('unsupported codec'));
        $telegramClient->expects($this->once())
            ->method('sendDocument')
            ->with(12_345, \sprintf('%s/clip.mkv', $directory), 'clip.mkv · best')
            ->willReturn(57);

        $this->handler(
            $task,
            $sourceRepository,
            $telegramClient,
            $taskManager,
            $this->createStub(LoggerInterface::class),
        )->__invoke(new TelegramDeliveryMessage(7));
    }

    /**
     * Verifies an unreachable upload is retried before the delivery is failed.
     */
    public function testFailingUploadIsRetriedBeforeTheDeliveryFails(): void
    {
        $directory = $this->createDirectory();
        $task      = $this->telegramTask();
        $source    = $this->source($task, $directory, 'broken.mp4');
        \file_put_contents(\sprintf('%s/broken.mp4', $directory), 'video');

        $sourceRepository = $this->createStub(SourceRepositoryInterface::class);
        $sourceRepository->method('findByDownloadTask')->willReturn([$source]);

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_FAILED);

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->exactly(3))
            ->method('sendVideo')
            ->willThrowException(new \RuntimeException('bot is down'));
        $telegramClient->expects($this->exactly(3))
            ->method('sendDocument')
            ->willThrowException(new \RuntimeException('bot is down'));

        $logger = $this->createStub(LoggerInterface::class);
        $errors = [];
        $logger->method('error')->willReturnCallback(
            static function (string $message, array $context = []) use (&$errors): void {
                $errors[] = [$message, $context];
            },
        );

        $telegramClient->expects($this->once())
            ->method('sendMessage')
            ->willThrowException(new \RuntimeException('bot is down'));

        $this->handler(
            $task,
            $sourceRepository,
            $telegramClient,
            $taskManager,
            $logger,
        )->__invoke(new TelegramDeliveryMessage(7));

        $this->assertCount(2, $errors);
        $this->assertSame('Telegram delivery failed', $errors[0][0]);
        $this->assertSame('Failed to send a Telegram delivery failure message', $errors[1][0]);
    }

    /**
     * Verifies a delivered file that cannot be unlinked is logged, not fatal.
     */
    public function testFileThatCannotBeDeletedIsLogged(): void
    {
        $directory = $this->createDirectory();
        $task      = $this->telegramTask();
        $source    = $this->source($task, $directory, 'readonly.mp3');
        \file_put_contents(\sprintf('%s/readonly.mp3', $directory), 'audio');
        \chmod($directory, 0555);

        $sourceRepository = $this->createStub(SourceRepositoryInterface::class);
        $sourceRepository->method('findByDownloadTask')->willReturn([$source]);

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_SENT, 58);

        $telegramClient = $this->createStub(TelegramClientInterface::class);
        $telegramClient->method('sendAudio')->willReturn(58);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to delete a delivered file', $this->arrayHasKey('path'));

        $this->handler(
            $task,
            $sourceRepository,
            $telegramClient,
            $taskManager,
            $logger,
        )->__invoke(new TelegramDeliveryMessage(7));

        $this->assertFileExists(\sprintf('%s/readonly.mp3', $directory));
    }

    private function telegramTask(
        string $deliveryStatus = DownloadTask::DELIVERY_PENDING,
        ?string $title = null,
        bool $linked = true,
    ): DownloadTask {
        $user = new User();

        $user->setEmail('delivery@test.local')
            ->setPassword('irrelevant-hash')
            ->setTelegramUserId($linked ? 12_345 : null)
        ;

        $task = new DownloadTask();

        $task->setUrl('https://test.local/video')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
            ->setUser($user)
            ->setTitle($title)
            ->setDeliveryStatus($deliveryStatus)
        ;
        new \ReflectionProperty(DownloadTask::class, 'id')->setValue($task, 7);

        return $task;
    }

    private function source(DownloadTask $task, string $directory, string $filename, float $size = 1.0): Source
    {
        $source = new Source();

        $source->setFilepath($directory)->setFilename($filename)->setSize($size)->setDownloadTask($task);

        return $source;
    }

    private function createDirectory(): string
    {
        $this->directory = \sprintf('%s/ytdownloader-delivery-%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(6)));
        \mkdir($this->directory);

        return $this->directory;
    }

    private function handler(
        DownloadTask $task,
        SourceRepositoryInterface $sourceRepository,
        TelegramClientInterface $telegramClient,
        DownloadTaskManagerInterface $taskManager,
        LoggerInterface $logger,
        ?AuditLoggerInterface $auditLogger = null,
        ?EntityManagerInterface $entityManager = null,
        int $maxUploadMb = 50,
    ): TelegramDeliveryMessageHandler {
        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        return new TelegramDeliveryMessageHandler(
            $taskRepository,
            $sourceRepository,
            $telegramClient,
            $taskManager,
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $auditLogger ?? $this->createStub(AuditLoggerInterface::class),
            $logger,
            $maxUploadMb,
            'https://test.local',
        );
    }
}
