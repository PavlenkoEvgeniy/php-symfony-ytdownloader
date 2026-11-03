<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\DownloadTask;
use App\Enum\DownloadOutcome;
use App\Message\DownloadMessage;
use App\MessageHandler\DownloadMessageHandler;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Service\AuditLoggerInterface;
use App\Service\DownloadTaskManager;
use App\Service\VideoProcessorInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

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

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->expects($this->once())
            ->method('find')
            ->with(DownloadTask::class, 7)
            ->willReturn($task);
        $em->expects($this->once())->method('flush');

        $taskManager = new DownloadTaskManager($em);

        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->once())
            ->method('process')
            ->with('https://test.local', 'best', '12345', 7)
            ->willReturn(DownloadOutcome::SUCCESS);

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn(null);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            $taskManager,
            $this->createStub(AuditLoggerInterface::class),
            $taskRepository,
            $bus,
        );

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));

        $this->assertSame(DownloadTask::STATUS_SUCCESS, $task->getStatus());
    }

    /**
     * Verifies a failed download marks the claimed task as errored.
     */
    public function testInvokeMarksTaskErrorWhenProcessingFails(): void
    {
        $task = new DownloadTask();
        $task->setUrl('https://test.local')->setQuality('best');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->willReturn(1);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->expects($this->once())
            ->method('find')
            ->with(DownloadTask::class, 7)
            ->willReturn($task);
        $em->expects($this->once())->method('flush');

        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->once())
            ->method('process')
            ->with('https://test.local', 'best', '12345', 7)
            ->willReturn(DownloadOutcome::ERROR);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            new DownloadTaskManager($em),
            $this->createStub(AuditLoggerInterface::class),
            $this->createStub(DownloadTaskRepositoryInterface::class),
            $bus,
        );

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));

        $this->assertSame(DownloadTask::STATUS_ERROR, $task->getStatus());
    }

    /**
     * Verifies the handler skips processing when the task cannot be claimed.
     */
    public function testInvokeSkipsMessageWhenClaimFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->expects($this->never())->method('find');

        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->never())->method('process');

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            new DownloadTaskManager($em),
            $this->createStub(AuditLoggerInterface::class),
            $this->createStub(DownloadTaskRepositoryInterface::class),
            $this->createStub(MessageBusInterface::class),
        );

        $handler->__invoke(new DownloadMessage('https://test.local', 'best', '12345', 7));
    }

    /**
     * Verifies a message without a task id is processed without touching tasks.
     */
    public function testInvokeLeavesTasksUntouchedWithoutTaskId(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getConnection');
        $em->expects($this->never())->method('find');
        $em->expects($this->never())->method('flush');

        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->once())
            ->method('process')
            ->with('https://test.local/2', 'audio', '', null)
            ->willReturn(DownloadOutcome::SUCCESS);

        $handler = new DownloadMessageHandler(
            $videoProcessor,
            new DownloadTaskManager($em),
            $this->createStub(AuditLoggerInterface::class),
            $this->createStub(DownloadTaskRepositoryInterface::class),
            $this->createStub(MessageBusInterface::class),
        );

        $handler->__invoke(new DownloadMessage('https://test.local/2', 'audio'));
    }
}
