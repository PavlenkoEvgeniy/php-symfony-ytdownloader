<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Dto\DownloadResult;
use App\Entity\DownloadTask;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use App\Enum\DownloadFailureKind;
use App\Message\DownloadMessage;
use App\Message\TelegramDeliveryMessage;
use App\MessageHandler\DownloadMessageHandler;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Service\AuditLoggerInterface;
use App\Service\DownloadTaskManager;
use App\Service\DownloadTaskManagerInterface;
use App\Service\TaskProcessingLeaseInterface;
use App\Service\TaskProcessingLockInterface;
use App\Service\TelegramNotifierInterface;
use App\Service\TelegramUpdateHandlerInterface;
use App\Service\VideoProcessorInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class DownloadMessageHandlerTest extends TestCase
{
    /**
     * Verifies the handler claims the task, processes it and marks it successful.
     */
    public function testInvokeClaimsTaskAndMarksSuccess(): void
    {
        $task = new DownloadTask();
        $task->setUrl('https://test.local')->setQuality('best');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects($this->once())
            ->method('find')
            ->with(DownloadTask::class, 7)
            ->willReturn($task);
        $entityManager->expects($this->once())->method('flush');

        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->once())
            ->method('process')
            ->with('https://test.local', 'best', '12345', 7)
            ->willReturn(DownloadResult::success());

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $repository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $repository->method('existsById')->willReturn(true);
        $repository->method('findById')->willReturn($task);

        $handler = $this->handler($videoProcessor, new DownloadTaskManager($entityManager), $bus, $repository);

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));

        $this->assertSame(DownloadTask::STATUS_SUCCESS, $task->getStatus());
    }

    /**
     * Verifies a failed download marks the claimed task as errored with its reason.
     */
    public function testInvokeMarksTaskErrorWhenProcessingFails(): void
    {
        $task = new DownloadTask();
        $task->setUrl('https://test.local')->setQuality('best');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects($this->once())
            ->method('find')
            ->with(DownloadTask::class, 7)
            ->willReturn($task);
        $entityManager->expects($this->once())->method('flush');

        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->once())
            ->method('process')
            ->with('https://test.local', 'best', '12345', 7)
            ->willReturn(DownloadResult::failure('video not found', DownloadFailureKind::PERMANENT));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $repository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $repository->method('existsById')->willReturn(true);
        $repository->method('findById')->willReturn($task);

        $handler = $this->handler($videoProcessor, new DownloadTaskManager($entityManager), $bus, $repository);

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));

        $this->assertSame(DownloadTask::STATUS_ERROR, $task->getStatus());
        $this->assertSame('video not found', $task->getErrorMessage());
    }

    /**
     * Verifies the handler skips processing when the task cannot be claimed.
     */
    public function testInvokeSkipsMessageWhenClaimFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->willReturn(0);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects($this->never())->method('find');

        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->never())->method('process');

        $handler = $this->handler($videoProcessor, new DownloadTaskManager($entityManager), $this->createStub(MessageBusInterface::class));

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));
    }

    /**
     * Verifies a message for a task with a live attempt is ignored before claiming.
     */
    public function testInvokeSkipsMessageWhenAnotherAttemptIsLive(): void
    {
        $lock = $this->createMock(TaskProcessingLockInterface::class);
        $lock->expects($this->once())->method('tryAcquire')->with(7)->willReturn(null);

        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->never())->method('process');

        $manager = $this->createMock(DownloadTaskManagerInterface::class);
        $manager->expects($this->never())->method('claimProcessing');

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            $manager,
            $this->createStub(AuditLoggerInterface::class),
            $this->createStub(DownloadTaskRepositoryInterface::class),
            $this->createStub(MessageBusInterface::class),
            $lock,
            $this->createStub(TelegramNotifierInterface::class),
            $this->createStub(TelegramUpdateHandlerInterface::class),
        );

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));
    }

    /**
     * Verifies a message without a task id is processed without touching tasks.
     */
    public function testInvokeLeavesTasksUntouchedWithoutTaskId(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getConnection');
        $entityManager->expects($this->never())->method('find');
        $entityManager->expects($this->never())->method('flush');

        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->once())
            ->method('process')
            ->with('https://test.local/2', 'audio', '', null)
            ->willReturn(DownloadResult::success());

        $handler = $this->handler($videoProcessor, new DownloadTaskManager($entityManager), $this->createStub(MessageBusInterface::class));

        $handler->__invoke(new DownloadMessage('https://test.local/2', 'audio'));
    }

    /**
     * Verifies a transient failure schedules another attempt instead of finalizing.
     */
    public function testInvokeSchedulesRetryForTransientFailure(): void
    {
        $task = new DownloadTask();
        $task->setUrl('https://test.local')->setQuality('best')->setStatus(DownloadTask::STATUS_PROCESSING);
        $task->setAttempts(1);

        $manager = $this->createMock(DownloadTaskManagerInterface::class);
        $manager->method('claimProcessing')->willReturn(true);
        $manager->expects($this->once())
            ->method('markRetryScheduled')
            ->with(7, 'temporary network failure');
        $manager->expects($this->never())->method('markError');

        $videoProcessor = $this->createStub(VideoProcessorInterface::class);
        $videoProcessor->method('process')->willReturn(
            DownloadResult::failure('temporary network failure', DownloadFailureKind::TRANSIENT),
        );

        $repository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $repository->method('findById')->willReturn($task);

        $repository->method('existsById')->willReturn(true);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(DownloadMessage::class),
                $this->callback(static function (array $stamps): bool {
                    return isset($stamps[0]) && $stamps[0] instanceof DelayStamp;
                }),
            )
            ->willReturn(new Envelope(new DownloadMessage('https://test.local', 'best', '12345', 7)));

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            $manager,
            $this->createStub(AuditLoggerInterface::class),
            $repository,
            $bus,
            $this->lock(),
            $this->createStub(TelegramNotifierInterface::class),
            $this->createStub(TelegramUpdateHandlerInterface::class),
        );

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));
    }

    /**
     * Verifies a Telegram download is audited under the task user's email.
     */
    public function testInvokeAuditsTelegramDownloadUnderUserEmail(): void
    {
        $user = new User()->setEmail('telegram@test.local');

        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
            ->setUser($user);

        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('find')->willReturn($task);
        $entityManager->method('flush');

        $videoProcessor = $this->createStub(VideoProcessorInterface::class);
        $videoProcessor->method('process')->willReturn(
            DownloadResult::failure('video not found', DownloadFailureKind::PERMANENT),
        );

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $taskRepository->method('existsById')->willReturn(true);

        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with(
                AuditAction::DOWNLOAD_FAILURE,
                AuditChannel::TELEGRAM,
                'download_task',
                7,
                'https://test.local',
                false,
                [
                    'quality'          => 'best',
                    'will_retry'       => false,
                    'reason'           => 'video not found',
                    'telegram_chat_id' => 12_345,
                ],
                null,
                null,
                $user,
            );

        $notifier = $this->createMock(TelegramNotifierInterface::class);
        $notifier->expects($this->once())
            ->method('notifyDownloadFailure')
            ->with('12345', 7);

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            new DownloadTaskManager($entityManager),
            $auditLogger,
            $taskRepository,
            $this->createStub(MessageBusInterface::class),
            $this->lock(),
            $notifier,
            $this->createStub(TelegramUpdateHandlerInterface::class),
        );

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));
    }

    /**
     * Verifies a Telegram download without a task user falls back to the chat id.
     */
    public function testInvokeAuditsTelegramFallbackWithoutTaskUser(): void
    {
        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345);

        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('find')->willReturn($task);
        $entityManager->method('flush');

        $videoProcessor = $this->createStub(VideoProcessorInterface::class);
        $videoProcessor->method('process')->willReturn(
            DownloadResult::failure('video not found', DownloadFailureKind::PERMANENT),
        );

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $taskRepository->method('existsById')->willReturn(true);

        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with(
                AuditAction::DOWNLOAD_FAILURE,
                AuditChannel::TELEGRAM,
                'download_task',
                7,
                'https://test.local',
                false,
                [
                    'quality'          => 'best',
                    'will_retry'       => false,
                    'reason'           => 'video not found',
                    'telegram_chat_id' => 12_345,
                ],
                AuditActorType::TELEGRAM,
                '12345',
                null,
            );

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            new DownloadTaskManager($entityManager),
            $auditLogger,
            $taskRepository,
            $this->createStub(MessageBusInterface::class),
            $this->lock(),
            $this->createStub(TelegramNotifierInterface::class),
            $this->createStub(TelegramUpdateHandlerInterface::class),
        );

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));
    }

    /**
     * Verifies a successful Telegram download schedules its delivery.
     */
    public function testInvokeSchedulesTelegramDeliveryOnSuccess(): void
    {
        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
        ;
        $this->setTaskId($task, 7);

        $manager = $this->createMock(DownloadTaskManagerInterface::class);
        $manager->method('claimProcessing')->willReturn(true);
        $manager->expects($this->once())->method('markSuccess')->with(7);
        $manager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_PENDING);

        $videoProcessor = $this->createStub(VideoProcessorInterface::class);
        $videoProcessor->method('process')->willReturn(DownloadResult::success());

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $taskRepository->method('existsById')->willReturn(true);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (object $message): bool => $message instanceof TelegramDeliveryMessage && 7 === $message->taskId))
            ->willReturn(new Envelope(new TelegramDeliveryMessage(7)));

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            $manager,
            $this->createStub(AuditLoggerInterface::class),
            $taskRepository,
            $bus,
            $this->lock(),
            $this->createStub(TelegramNotifierInterface::class),
            $this->createStub(TelegramUpdateHandlerInterface::class),
        );

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));
    }

    /**
     * Verifies a failure without a usable chat id notifies nobody.
     */
    public function testInvokeSkipsFailureNotificationWithoutUsableChat(): void
    {
        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(0)
        ;

        $manager = $this->createMock(DownloadTaskManagerInterface::class);
        $manager->method('claimProcessing')->willReturn(true);
        $manager->expects($this->once())->method('markError')->with(7, 'video not found');

        $videoProcessor = $this->createStub(VideoProcessorInterface::class);
        $videoProcessor->method('process')->willReturn(
            DownloadResult::failure('video not found', DownloadFailureKind::PERMANENT),
        );

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $taskRepository->method('existsById')->willReturn(true);

        $notifier = $this->createMock(TelegramNotifierInterface::class);
        $notifier->expects($this->never())->method('notifyDownloadFailure');

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            $manager,
            $this->createStub(AuditLoggerInterface::class),
            $taskRepository,
            $this->createStub(MessageBusInterface::class),
            $this->lock(),
            $notifier,
            $this->createStub(TelegramUpdateHandlerInterface::class),
        );

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '42', 7));
    }

    /**
     * Verifies a size rejection is reported with the actionable message, not the generic one.
     */
    public function testInvokeSendsSizeRejectionMessageForOversizedDownload(): void
    {
        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
        ;

        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('find')->willReturn($task);
        $entityManager->method('flush');

        $videoProcessor = $this->createStub(VideoProcessorInterface::class);
        $videoProcessor->method('process')->willReturn(
            DownloadResult::sizeExceeded('Estimated file size 60 MB exceeds the Telegram upload limit of 50 MB.'),
        );

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $taskRepository->method('existsById')->willReturn(true);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $notifier = $this->createMock(TelegramNotifierInterface::class);
        $notifier->expects($this->once())->method('notifyFileTooLarge')->with('12345');
        $notifier->expects($this->never())->method('notifyDownloadFailure');

        $updateHandler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $updateHandler->expects($this->once())
            ->method('offerQualitySelection')
            ->with(12_345, 12_345, 'https://test.local', 'https://test.local', 'best');

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            new DownloadTaskManager($entityManager),
            $this->createStub(AuditLoggerInterface::class),
            $taskRepository,
            $bus,
            $this->lock(),
            $notifier,
            $updateHandler,
        );

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));

        $this->assertSame(DownloadTask::STATUS_ERROR, $task->getStatus());
        $this->assertStringContainsString('Telegram upload limit', (string) $task->getErrorMessage());
    }

    /**
     * Verifies a transfer-cap refusal also reaches the dedicated size message.
     */
    public function testInvokeSendsSizeMessageForTransferCapRefusal(): void
    {
        $task = new DownloadTask();

        $task->setUrl('https://test.local')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
        ;

        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('find')->willReturn($task);
        $entityManager->method('flush');

        $videoProcessor = $this->createStub(VideoProcessorInterface::class);
        $videoProcessor->method('process')->willReturn(
            DownloadResult::sizeExceeded('File size 2755 MB exceeds the Telegram upload limit of 2000 MB. Choose another quality.'),
        );

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $taskRepository->method('existsById')->willReturn(true);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $notifier = $this->createMock(TelegramNotifierInterface::class);
        $notifier->expects($this->once())->method('notifyFileTooLarge')->with('12345');
        $notifier->expects($this->never())->method('notifyDownloadFailure');

        $updateHandler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $updateHandler->expects($this->once())
            ->method('offerQualitySelection')
            ->with(12_345, 12_345, 'https://test.local', 'https://test.local', 'best');

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            new DownloadTaskManager($entityManager),
            $this->createStub(AuditLoggerInterface::class),
            $taskRepository,
            $bus,
            $this->lock(),
            $notifier,
            $updateHandler,
        );

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));

        $this->assertSame(DownloadTask::STATUS_ERROR, $task->getStatus());
        $this->assertStringContainsString('Telegram upload limit', (string) $task->getErrorMessage());
    }

    /**
     * Verifies a task removed by /cancel mid-attempt sends no notification:
     * the reload finds nothing and the notification branch needs a live task.
     */
    public function testCancelledTaskSendsNoNotification(): void
    {
        $manager = $this->createMock(DownloadTaskManagerInterface::class);
        $manager->method('claimProcessing')->willReturn(true);
        $manager->expects($this->never())->method('markError');
        $manager->expects($this->never())->method('markSuccess');

        $videoProcessor = $this->createStub(VideoProcessorInterface::class);
        $videoProcessor->method('process')->willReturn(
            DownloadResult::failure('Download cancelled by user.', DownloadFailureKind::PERMANENT),
        );

        // /cancel removed the row mid-attempt; only the identity map still
        // holds the entity, which findById alone would happily return.
        $stale = new DownloadTask();

        $stale->setUrl('https://test.local')->setQuality('best');

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('existsById')->willReturn(false);
        $taskRepository->method('findById')->willReturn($stale);

        $notifier = $this->createMock(TelegramNotifierInterface::class);
        $notifier->expects($this->never())->method('notifyDownloadFailure');
        $notifier->expects($this->never())->method('notifyFileTooLarge');

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            $manager,
            $this->createStub(AuditLoggerInterface::class),
            $taskRepository,
            $this->createStub(MessageBusInterface::class),
            $this->lock(),
            $notifier,
            $this->createStub(TelegramUpdateHandlerInterface::class),
        );

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));
    }

    private function handler(
        VideoProcessorInterface $videoProcessor,
        DownloadTaskManagerInterface $manager,
        MessageBusInterface $bus,
        ?DownloadTaskRepositoryInterface $repository = null,
    ): DownloadMessageHandler {
        return new DownloadMessageHandler(
            $videoProcessor,
            $manager,
            $this->createStub(AuditLoggerInterface::class),
            $repository ?? $this->createStub(DownloadTaskRepositoryInterface::class),
            $bus,
            $this->lock(),
            $this->createStub(TelegramNotifierInterface::class),
            $this->createStub(TelegramUpdateHandlerInterface::class),
        );
    }

    private function lock(): TaskProcessingLockInterface
    {
        $lease = $this->createStub(TaskProcessingLeaseInterface::class);

        $lock = $this->createStub(TaskProcessingLockInterface::class);
        $lock->method('tryAcquire')->willReturn($lease);

        return $lock;
    }

    private function setTaskId(DownloadTask $task, int $id): void
    {
        new \ReflectionProperty(DownloadTask::class, 'id')->setValue($task, $id);
    }
}
