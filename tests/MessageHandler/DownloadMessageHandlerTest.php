<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\DownloadTask;
use App\Message\DownloadMessage;
use App\MessageHandler\DownloadMessageHandler;
use App\Service\DownloadTaskManager;
use App\Service\VideoProcessorInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class DownloadMessageHandlerTest extends TestCase
{
    public function testInvokeClaimsTaskAndMarksSuccess(): void
    {
        $task = new DownloadTask();
        $task->setUrl('https://example.com')->setQuality('best');

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
            ->with('https://example.com', 'best', '12345');

        $handler = new DownloadMessageHandler($videoProcessor, $taskManager);

        $handler->__invoke(new DownloadMessage('https://example.com', 'best', '12345', 7));

        $this->assertSame(DownloadTask::STATUS_SUCCESS, $task->getStatus());
    }

    public function testInvokeSkipsMessageWhenClaimFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->expects($this->never())->method('find');

        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->never())->method('process');

        $handler = new DownloadMessageHandler($videoProcessor, new DownloadTaskManager($em));

        $handler->__invoke(new DownloadMessage('https://example.com', 'best', '12345', 7));
    }

    public function testInvokeLeavesTasksUntouchedWithoutTaskId(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getConnection');
        $em->expects($this->never())->method('find');
        $em->expects($this->never())->method('flush');

        $videoProcessor = $this->createMock(VideoProcessorInterface::class);
        $videoProcessor->expects($this->once())
            ->method('process')
            ->with('https://example.com/2', 'audio', '');

        $handler = new DownloadMessageHandler($videoProcessor, new DownloadTaskManager($em));

        $handler->__invoke(new DownloadMessage('https://example.com/2', 'audio'));
    }
}
