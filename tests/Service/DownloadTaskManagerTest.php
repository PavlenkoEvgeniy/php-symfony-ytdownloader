<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadTask;
use App\Service\DownloadTaskManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class DownloadTaskManagerTest extends TestCase
{
    public function testClaimProcessingUpdatesQueuedTask(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE download_task SET status = :processing WHERE id = :id AND status = :queued',
                [
                    'id'         => 1,
                    'processing' => DownloadTask::STATUS_PROCESSING,
                    'queued'     => DownloadTask::STATUS_QUEUED,
                ],
            )
            ->willReturn(1);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('getConnection')->willReturn($connection);

        $this->assertTrue((new DownloadTaskManager($em))->claimProcessing(1));
    }

    public function testClaimProcessingReturnsFalseWhenTaskIsNotQueued(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        $this->assertFalse((new DownloadTaskManager($em))->claimProcessing(1));
    }

    public function testMarkSuccessSetsStatus(): void
    {
        $task = new DownloadTask();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($task);

        (new DownloadTaskManager($em))->markSuccess(1);

        $this->assertSame(DownloadTask::STATUS_SUCCESS, $task->getStatus());
    }

    public function testMarkErrorSetsStatus(): void
    {
        $task = new DownloadTask();
        $task->setUrl('https://example.com')->setQuality('best');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($task);

        (new DownloadTaskManager($em))->markError(1);

        $this->assertSame(DownloadTask::STATUS_ERROR, $task->getStatus());
    }

    public function testMarkStatusIgnoresMissingTask(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('find')->willReturn(null);
        $em->expects($this->never())->method('flush');

        (new DownloadTaskManager($em))->markError(99);

        $this->assertSame(DownloadTask::STATUS_QUEUED, (new DownloadTask())->getStatus());
    }
}
