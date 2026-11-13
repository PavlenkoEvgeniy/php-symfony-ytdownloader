<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DownloadTask;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DownloadTask>
 */
final class DownloadTaskRepository extends ServiceEntityRepository implements DownloadTaskRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, DownloadTask::class);
    }

    #[\Override]
    public function findById(int $id): ?DownloadTask
    {
        $task = $this->find($id);

        return $task instanceof DownloadTask ? $task : null;
    }

    #[\Override]
    /**
     * Scalar existence probe that bypasses the identity map, so a task
     * deleted by another connection is seen as gone immediately.
     */
    public function existsById(int $id): bool
    {
        return 0 < (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[\Override]
    /**
     * @return DownloadTask[]
     */
    public function findActiveByUser(User $user, int $limit = 10): array
    {
        /** @var DownloadTask[] $tasks */
        $tasks = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                DownloadTask::STATUS_QUEUED,
                DownloadTask::STATUS_PROCESSING,
            ], ArrayParameterType::STRING)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $tasks;
    }

    #[\Override]
    public function countActiveByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                DownloadTask::STATUS_QUEUED,
                DownloadTask::STATUS_PROCESSING,
            ], ArrayParameterType::STRING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[\Override]
    /**
     * @return DownloadTask[]
     */
    public function findQueuedByUser(User $user): array
    {
        /** @var DownloadTask[] $tasks */
        $tasks = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', DownloadTask::STATUS_QUEUED)
            ->orderBy('t.createdAt')
            ->getQuery()
            ->getResult()
        ;

        return $tasks;
    }

    #[\Override]
    public function deleteQueuedByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', DownloadTask::STATUS_QUEUED)
            ->getQuery()
            ->execute();
    }

    #[\Override]
    /**
     * Removes every active task (queued and processing) of a user and
     * returns their number; /cancel uses it to free the user's slot.
     */
    public function deleteActiveByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                DownloadTask::STATUS_QUEUED,
                DownloadTask::STATUS_PROCESSING,
            ], ArrayParameterType::STRING)
            ->getQuery()
            ->execute();
    }

    #[\Override]
    public function findActiveByUserAndUrl(User $user, string $url): ?DownloadTask
    {
        $task = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.url = :url')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('url', $url)
            ->setParameter('statuses', [
                DownloadTask::STATUS_QUEUED,
                DownloadTask::STATUS_PROCESSING,
            ], ArrayParameterType::STRING)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $task instanceof DownloadTask ? $task : null;
    }

    #[\Override]
    /**
     * Active tasks are the ones not yet finished (queued, processing, error).
     * The limit is a display-only guard: error tasks stay active until re-downloaded successfully.
     *
     * @return DownloadTask[]
     */
    public function getRecentActiveTasks(int $limit = 50): array
    {
        /** @var DownloadTask[] $tasks */
        $tasks = $this->createQueryBuilder('t')
            ->where('t.status IN (:statuses)')
            ->setParameter('statuses', [
                DownloadTask::STATUS_QUEUED,
                DownloadTask::STATUS_PROCESSING,
                DownloadTask::STATUS_ERROR,
            ], ArrayParameterType::STRING)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $tasks;
    }

    #[\Override]
    /**
     * Bulk-deletes every task still waiting in the queue (queued status) and returns their number.
     */
    public function deleteQueued(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.status = :status')
            ->setParameter('status', DownloadTask::STATUS_QUEUED)
            ->getQuery()
            ->execute();
    }

    #[\Override]
    /**
     * Bulk-deletes every failed task (error status) so the Active Tasks list
     * can be cleared, and returns their number.
     */
    public function deleteErrorTasks(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.status = :status')
            ->setParameter('status', DownloadTask::STATUS_ERROR)
            ->getQuery()
            ->execute();
    }

    #[\Override]
    /**
     * @return DownloadTask[]
     */
    public function findProcessingTasks(): array
    {
        /** @var DownloadTask[] $tasks */
        $tasks = $this->createQueryBuilder('t')
            ->where('t.status = :processing')
            ->setParameter('processing', DownloadTask::STATUS_PROCESSING)
            ->orderBy('t.id')
            ->getQuery()
            ->getResult()
        ;

        return $tasks;
    }

    #[\Override]
    /**
     * Resets one stuck processing task back to queued. The caller decides whether
     * the task is really stuck (no live processing lease) before calling this.
     */
    public function resetTask(int $taskId): bool
    {
        $affected = (int) $this->createQueryBuilder('t')
            ->update()
            ->set('t.status', ':queued')
            ->set('t.startedAt', ':startedAt')
            ->set('t.errorMessage', ':errorMessage')
            ->where('t.id = :id')
            ->andWhere('t.status = :processing')
            ->setParameter('id', $taskId)
            ->setParameter('queued', DownloadTask::STATUS_QUEUED)
            ->setParameter('processing', DownloadTask::STATUS_PROCESSING)
            ->setParameter('startedAt', null)
            ->setParameter('errorMessage', null)
            ->getQuery()
            ->execute();

        return $affected > 0;
    }

    #[\Override]
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
