<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadTask;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Service\QueueResetServiceInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class QueueResetServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private DownloadTaskRepositoryInterface $downloadTaskRepository;
    private QueueResetServiceInterface $queueResetService;

    public function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager                = self::getContainer()->get('doctrine')->getManager();
        $this->entityManager          = $entityManager;
        $this->connection             = $entityManager->getConnection();
        $this->downloadTaskRepository = self::getContainer()->get(DownloadTaskRepositoryInterface::class);
        $this->queueResetService      = self::getContainer()->get(QueueResetServiceInterface::class);

        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollback();
            $this->entityManager->clear();
        }

        parent::tearDown();
    }

    private function createTask(string $status): DownloadTask
    {
        $task = new DownloadTask();
        $task->setUrl('https://youtube.com/watch?v=test')->setQuality('best')->setStatus($status);
        $this->entityManager->persist($task);

        return $task;
    }

    private function insertMessage(string $queueName, ?string $deliveredAt): void
    {
        $this->connection->executeStatement(
            'INSERT INTO messenger_messages (body, headers, queue_name, created_at, available_at, delivered_at)
             VALUES (:body, :headers, :queueName, NOW(), NOW(), :deliveredAt)',
            [
                'body'        => '{}',
                'headers'     => '[]',
                'queueName'   => $queueName,
                'deliveredAt' => $deliveredAt,
            ],
        );
    }

    /**
     * Verifies reset moves processing tasks to queued and dispatches a fresh message for each.
     */
    public function testResetReturnsOnlyProcessingTasksToQueued(): void
    {
        $before         = $this->downloadTaskRepository->getStatusCounts();
        $beforeMessages = (int) $this->connection->executeQuery(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'download_queue'",
        )->fetchOne();

        $this->createTask(DownloadTask::STATUS_PROCESSING);
        $this->createTask(DownloadTask::STATUS_PROCESSING);
        $this->createTask(DownloadTask::STATUS_QUEUED);
        $this->createTask(DownloadTask::STATUS_ERROR);
        $this->createTask(DownloadTask::STATUS_SUCCESS);
        $this->entityManager->flush();

        $result = $this->queueResetService->reset();

        $counts = $this->downloadTaskRepository->getStatusCounts();
        $this->assertSame($before[DownloadTask::STATUS_PROCESSING] + 2, $result['processing']);
        $this->assertSame(0, $counts[DownloadTask::STATUS_PROCESSING]);
        $this->assertSame($before[DownloadTask::STATUS_QUEUED] + 3, $counts[DownloadTask::STATUS_QUEUED]);
        $this->assertSame($before[DownloadTask::STATUS_ERROR] + 1, $counts[DownloadTask::STATUS_ERROR]);
        $this->assertSame($before[DownloadTask::STATUS_SUCCESS] + 1, $counts[DownloadTask::STATUS_SUCCESS]);

        // Each reset task gets a fresh message, even if the old one was already acked.
        $afterMessages = (int) $this->connection->executeQuery(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'download_queue'",
        )->fetchOne();
        $this->assertSame($beforeMessages + 2, $afterMessages);
    }

    /**
     * Verifies reset redelivers in-flight download messages and leaves other queues alone.
     */
    public function testResetRedeliversInFlightMessagesOfDownloadQueueOnly(): void
    {
        $beforeStuck = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NOT NULL',
        )->fetchOne();
        $beforeStuckResettable = (int) $this->connection->executeQuery(
            "SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NOT NULL AND queue_name = 'download_queue'",
        )->fetchOne();

        $this->insertMessage('download_queue', '2026-01-01 00:00:00');
        $this->insertMessage('download_queue', null);
        $this->insertMessage('failed_queue', '2026-01-01 00:00:00');
        $this->insertMessage('other_queue', '2026-01-01 00:00:00');

        $result = $this->queueResetService->reset();

        $this->assertSame($beforeStuckResettable + 1, $result['messages']);

        $remainingStuck = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NOT NULL',
        )->fetchOne();
        $this->assertSame($beforeStuck - $beforeStuckResettable + 2, $remainingStuck);
    }
}
