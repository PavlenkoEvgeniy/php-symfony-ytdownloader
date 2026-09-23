<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadTask;
use App\Service\QueueStatsService;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class QueueStatsServiceTest extends KernelTestCase
{
    private EntityManager $em;
    private QueueStatsService $queueStatsService;

    public function setUp(): void
    {
        // Skip functional DB tests if the database host is not resolvable in this environment
        $dbUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? \getenv('DATABASE_URL') ?: '';
        $parts = \parse_url($dbUrl);
        if (false !== $parts && isset($parts['host'])) {
            $host = $parts['host'];
            if (\gethostbyname($host) === $host) {
                $this->markTestSkipped('Database host not resolvable - skipping QueueStatsService tests.');
            }
        }

        self::bootKernel();

        /** @var EntityManager $em */
        $em                      = self::getContainer()->get('doctrine')->getManager();
        $this->em                = $em;
        $this->queueStatsService = self::getContainer()->get(QueueStatsService::class);

        $this->em->getConnection()->beginTransaction();
    }

    public function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollback();
            $this->em->clear();
        }

        parent::tearDown();
    }

    public function testGetStatsCountsPersistedTasks(): void
    {
        $task = new DownloadTask();
        $task->setUrl('https://youtube.com/watch?v=test')->setQuality('best');
        $this->em->persist($task);
        $this->em->flush();

        $stats = $this->queueStatsService->getStats();

        $this->assertSame(1, $stats['queued']);
        $this->assertSame(0, $stats['processing']);
        $this->assertSame(0, $stats['success']);
        $this->assertSame(0, $stats['error']);
        $this->assertCount(1, $stats['tasks']);
        $this->assertSame('https://youtube.com/watch?v=test', $stats['tasks'][0]['url']);
        $this->assertSame(DownloadTask::STATUS_QUEUED, $stats['tasks'][0]['status']);
        $this->assertStringContainsString('T', $stats['tasks'][0]['createdAt']);
    }
}
