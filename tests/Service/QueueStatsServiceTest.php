<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DownloadTask;
use App\Service\QueueStatsService;
use App\Service\SourceManager;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class QueueStatsServiceTest extends KernelTestCase
{
    private EntityManager $em;
    private QueueStatsService $queueStatsService;

    public function setUp(): void
    {
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

    /**
     * Verifies stats count a newly persisted task as queued without changing other counters.
     */
    public function testGetStatsCountsPersistedTasks(): void
    {
        $before = $this->queueStatsService->getStats();

        $task = new DownloadTask();
        $task->setUrl('https://youtube.com/watch?v=test')->setQuality('best');
        $this->em->persist($task);
        $this->em->flush();

        $stats = $this->queueStatsService->getStats();

        $this->assertSame($before['queued'] + 1, $stats['queued']);
        $this->assertSame($before['processing'], $stats['processing']);
        $this->assertSame($before['success'], $stats['success']);
        $this->assertSame($before['error'], $stats['error']);
        $this->assertSame($before['totalSize'], $stats['totalSize']);
    }

    /**
     * Verifies the lifetime total size does not shrink when a source is removed.
     */
    public function testTotalSizeSurvivesSourceRemoval(): void
    {
        $beforeTotal = $this->queueStatsService->getStats()['totalSize'];

        $sourceManager = self::getContainer()->get(SourceManager::class);

        $source = $sourceManager->createFromDownloadedFile('issue-77-fixture.mp4', '/tmp', 1234.0);
        $this->em->flush();

        $statsAfterDownload = $this->queueStatsService->getStats();
        $this->assertSame($beforeTotal + 1234, $statsAfterDownload['totalSize']);

        $this->em->remove($source);
        $this->em->flush();

        // Deleting the source never reduces the lifetime counter (issue #77).
        $stats = $this->queueStatsService->getStats();
        $this->assertSame($statsAfterDownload['totalSize'], $stats['totalSize']);
    }
}
