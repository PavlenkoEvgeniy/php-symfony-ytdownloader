<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DownloadTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DownloadTask>
 */
final class DownloadTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DownloadTask::class);
    }

    /**
     * @return DownloadTask[]
     */
    public function getActiveTasks(): array
    {
        return $this->findBy(
            ['status' => [
                DownloadTask::STATUS_QUEUED,
                DownloadTask::STATUS_PROCESSING,
                DownloadTask::STATUS_ERROR,
            ]],
            ['createdAt' => 'ASC']
        );
    }

    /**
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.status', 'COUNT(t.id) as cnt')
            ->groupBy('t.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [
            DownloadTask::STATUS_QUEUED     => 0,
            DownloadTask::STATUS_PROCESSING => 0,
            DownloadTask::STATUS_SUCCESS    => 0,
            DownloadTask::STATUS_ERROR      => 0,
        ];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }
}
