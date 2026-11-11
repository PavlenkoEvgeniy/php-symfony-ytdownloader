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
    /**
     * Verifies claiming a queued task switches it to processing.
     */
    public function testClaimProcessingUpdatesQueuedTask(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE download_task
                SET status = :processing, started_at = NOW(), attempts = attempts + 1, error_message = NULL
              WHERE id = :id AND status IN (:queued, :processing)',
                [
                    'id'         => 1,
                    'processing' => DownloadTask::STATUS_PROCESSING,
                    'queued'     => DownloadTask::STATUS_QUEUED,
                ],
            )
            ->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('getConnection')->willReturn($connection);

        $this->assertTrue(new DownloadTaskManager($entityManager)->claimProcessing(1));
    }

    /**
     * Verifies claiming fails when no queued row matches.
     */
    public function testClaimProcessingReturnsFalseWhenTaskIsNotQueued(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(0);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $this->assertFalse(new DownloadTaskManager($entityManager)->claimProcessing(1));
    }

    /**
     * Verifies marking a task successful updates its status.
     */
    public function testMarkSuccessSetsStatus(): void
    {
        $task = new DownloadTask();

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($task);

        new DownloadTaskManager($entityManager)->markSuccess(1);

        $this->assertSame(DownloadTask::STATUS_SUCCESS, $task->getStatus());
    }

    /**
     * Verifies marking a task errored updates its status.
     */
    public function testMarkErrorSetsStatus(): void
    {
        $task = new DownloadTask();
        $task->setUrl('https://test.local')->setQuality('best');

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($task);

        new DownloadTaskManager($entityManager)->markError(1, 'download failed');

        $this->assertSame(DownloadTask::STATUS_ERROR, $task->getStatus());
        $this->assertSame('download failed', $task->getErrorMessage());
        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getFinishedAt());
    }

    /**
     * Verifies marking a missing task does not flush anything.
     */
    public function testMarkStatusIgnoresMissingTask(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('find')->willReturn(null);
        $entityManager->expects($this->never())->method('flush');

        new DownloadTaskManager($entityManager)->markError(99);

        $this->assertSame(DownloadTask::STATUS_QUEUED, new DownloadTask()->getStatus());
    }

    /**
     * Verifies scheduling a retry requeues the processing task and keeps the reason.
     */
    public function testMarkRetryScheduledRequeuesProcessingTask(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE download_task
                SET status = :queued, error_message = :errorMessage, finished_at = NULL
              WHERE id = :id AND status = :processing',
                [
                    'id'           => 3,
                    'queued'       => DownloadTask::STATUS_QUEUED,
                    'processing'   => DownloadTask::STATUS_PROCESSING,
                    'errorMessage' => 'timed out',
                ],
            )
            ->willReturn(1);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        new DownloadTaskManager($entityManager)->markRetryScheduled(3, 'timed out');
    }

    /**
     * Verifies a sent delivery stores the delivery time and the bot message id.
     */
    public function testMarkDeliveryStatusSentStoresDeliveryTimeAndMessageId(): void
    {
        $task = new DownloadTask();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($task);
        $entityManager->expects($this->once())->method('flush');

        new DownloadTaskManager($entityManager)->markDeliveryStatus(1, DownloadTask::DELIVERY_SENT, 555);

        $this->assertSame(DownloadTask::DELIVERY_SENT, $task->getDeliveryStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getDeliveredAt());
        $this->assertSame(555, $task->getTelegramMessageId());
    }

    /**
     * Verifies a delivery that was not sent keeps the delivery time empty.
     */
    public function testMarkDeliveryStatusSkippedKeepsDeliveryTimeEmpty(): void
    {
        $task = new DownloadTask();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($task);
        $entityManager->expects($this->once())->method('flush');

        new DownloadTaskManager($entityManager)->markDeliveryStatus(1, DownloadTask::DELIVERY_SKIPPED);

        $this->assertSame(DownloadTask::DELIVERY_SKIPPED, $task->getDeliveryStatus());
        $this->assertNull($task->getDeliveredAt());
        $this->assertNull($task->getTelegramMessageId());
    }

    /**
     * Verifies a missing task does not flush a delivery status.
     */
    public function testMarkDeliveryStatusIgnoresMissingTask(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('find')->willReturn(null);
        $entityManager->expects($this->never())->method('flush');

        new DownloadTaskManager($entityManager)->markDeliveryStatus(99, DownloadTask::DELIVERY_SENT, 1);
    }
}
