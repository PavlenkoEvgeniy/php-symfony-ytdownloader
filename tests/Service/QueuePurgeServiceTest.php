<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadTask;
use App\Service\QueuePurgeService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class QueuePurgeServiceTest extends KernelTestCase
{
    private EntityManager $em;
    private Connection $connection;
    private QueuePurgeService $queuePurgeService;

    public function setUp(): void
    {
        // Skip functional DB tests if the database host is not resolvable in this environment
        $dbUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? \getenv('DATABASE_URL') ?: '';
        $parts = \parse_url($dbUrl);
        if (false !== $parts && isset($parts['host'])) {
            $host = $parts['host'];
            if (\gethostbyname($host) === $host) {
                $this->markTestSkipped('Database host not resolvable - skipping QueuePurgeService tests.');
            }
        }

        self::bootKernel();

        /** @var EntityManager $em */
        $em                      = self::getContainer()->get('doctrine')->getManager();
        $this->em                = $em;
        $this->connection        = $em->getConnection();
        $this->queuePurgeService = self::getContainer()->get(QueuePurgeService::class);

        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollback();
            $this->em->clear();
        }

        parent::tearDown();
    }

    private function createTask(string $status): DownloadTask
    {
        $task = new DownloadTask();
        $task->setUrl('https://youtube.com/watch?v=test')->setQuality('best')->setStatus($status);
        $this->em->persist($task);

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

    public function testPurgeDeletesOnlyQueuedTasks(): void
    {
        $before = $this->em->getRepository(DownloadTask::class)->getStatusCounts();

        $this->createTask(DownloadTask::STATUS_QUEUED);
        $this->createTask(DownloadTask::STATUS_QUEUED);
        $this->createTask(DownloadTask::STATUS_PROCESSING);
        $this->createTask(DownloadTask::STATUS_ERROR);
        $this->createTask(DownloadTask::STATUS_SUCCESS);
        $this->em->flush();

        $result = $this->queuePurgeService->purge();

        $counts = $this->em->getRepository(DownloadTask::class)->getStatusCounts();
        $this->assertSame($before[DownloadTask::STATUS_QUEUED] + 2, $result['tasks']);
        $this->assertSame(0, $counts[DownloadTask::STATUS_QUEUED]);
        $this->assertSame($before[DownloadTask::STATUS_PROCESSING] + 1, $counts[DownloadTask::STATUS_PROCESSING]);
        $this->assertSame($before[DownloadTask::STATUS_ERROR] + 1, $counts[DownloadTask::STATUS_ERROR]);
        $this->assertSame($before[DownloadTask::STATUS_SUCCESS] + 1, $counts[DownloadTask::STATUS_SUCCESS]);
    }

    public function testPurgeDeletesPendingMessagesOfBothTransports(): void
    {
        $before = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NULL',
        )->fetchOne();
        $beforePurged = (int) $this->connection->executeQuery(
            "SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NULL AND queue_name IN ('download_queue', 'failed_queue')",
        )->fetchOne();
        $beforeTasks = $this->em->getRepository(DownloadTask::class)->getStatusCounts();

        $this->insertMessage('download_queue', null);
        $this->insertMessage('download_queue', null);
        $this->insertMessage('failed_queue', null);
        $this->insertMessage('download_queue', '2026-01-01 00:00:00');
        $this->insertMessage('other_queue', null);

        $result = $this->queuePurgeService->purge();

        $this->assertSame($beforeTasks[DownloadTask::STATUS_QUEUED], $result['tasks']);
        $this->assertSame($beforePurged + 3, $result['messages']);

        $remaining = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NULL',
        )->fetchOne();
        $this->assertSame($before - $beforePurged + 1, $remaining);
    }
}
