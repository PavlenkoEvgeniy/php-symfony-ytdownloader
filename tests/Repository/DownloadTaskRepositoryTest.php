<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\DownloadTask;
use App\Entity\User;
use App\Repository\DownloadTaskRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DownloadTaskRepositoryTest extends KernelTestCase
{
    private const string URL_FORMAT = 'https://test.local/repository/%s';

    private EntityManagerInterface $entityManager;
    private DownloadTaskRepositoryInterface $repository;
    private User $user;
    private User $other;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository    = self::getContainer()->get(DownloadTaskRepositoryInterface::class);

        $this->entityManager->getConnection()->beginTransaction();

        $this->user = new User();

        $this->user->setEmail('task-repository@test.local')
            ->setPassword('irrelevant-hash')
            ->setIsEnabled(true);

        $this->other = new User();

        $this->other->setEmail('task-repository-other@test.local')
            ->setPassword('irrelevant-hash')
            ->setIsEnabled(true);

        $this->entityManager->persist($this->user);
        $this->entityManager->persist($this->other);
        $this->entityManager->flush();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollback();
            $this->entityManager->clear();
        }
    }

    /**
     * Verifies a stored task is found by id and an unknown id returns null.
     */
    public function testFindByIdReturnsTheStoredTask(): void
    {
        $task = $this->createTask();

        $this->assertSame($task, $this->repository->findById((int) $task->getId()));
        $this->assertNull($this->repository->findById(9_999_999));
    }

    /**
     * Verifies only queued and processing tasks count as active for a user.
     */
    public function testFindActiveByUserReturnsQueuedAndProcessingTasks(): void
    {
        $queued     = $this->createTask();
        $processing = $this->createTask(status: DownloadTask::STATUS_PROCESSING);
        $this->createTask(status: DownloadTask::STATUS_SUCCESS, urlSuffix: 'done');
        $this->createTask(status: DownloadTask::STATUS_ERROR, urlSuffix: 'broken');

        $active = $this->repository->findActiveByUser($this->user);

        $this->assertCount(2, $active);
        $this->assertContains($queued, $active);
        $this->assertContains($processing, $active);
    }

    /**
     * Verifies the active task limit is applied.
     */
    public function testFindActiveByUserRespectsTheLimit(): void
    {
        $this->createTask(urlSuffix: 'first');
        $this->createTask(urlSuffix: 'second');
        $this->createTask(urlSuffix: 'third');

        $this->assertCount(1, $this->repository->findActiveByUser($this->user, 1));
        $this->assertCount(3, $this->repository->findActiveByUser($this->user));
    }

    /**
     * Verifies the active task counter ignores finished tasks.
     */
    public function testCountActiveByUserCountsActiveTasksOnly(): void
    {
        $this->createTask();
        $this->createTask(status: DownloadTask::STATUS_PROCESSING, urlSuffix: 'running');
        $this->createTask(status: DownloadTask::STATUS_SUCCESS, urlSuffix: 'done');
        $this->createTask(status: DownloadTask::STATUS_ERROR, urlSuffix: 'broken');

        $this->assertSame(2, $this->repository->countActiveByUser($this->user));
        $this->assertSame(0, $this->repository->countActiveByUser($this->other));
    }

    /**
     * Verifies queued tasks of a user are listed separately from processing ones.
     */
    public function testFindQueuedByUserReturnsQueuedTasksOnly(): void
    {
        $queued     = $this->createTask();
        $processing = $this->createTask(status: DownloadTask::STATUS_PROCESSING, urlSuffix: 'running');

        $found = $this->repository->findQueuedByUser($this->user);

        $this->assertCount(1, $found);
        $this->assertContains($queued, $found);
        $this->assertNotContains($processing, $found);
    }

    /**
     * Verifies deleting queued tasks removes them for one user only and keeps running ones.
     */
    public function testDeleteQueuedByUserRemovesQueuedTasksOnly(): void
    {
        $queued = $this->createTask();
        $this->createTask(status: DownloadTask::STATUS_PROCESSING, urlSuffix: 'running');

        $other  = $this->other;
        $theirs = $this->createTask(user: $other, urlSuffix: 'theirs');

        $this->assertSame(1, $this->repository->deleteQueuedByUser($this->user));

        $this->entityManager->clear();
        $this->assertNull($this->repository->findById((int) $queued->getId()));
        $this->assertNotNull($this->repository->findById((int) $theirs->getId()));
        $this->assertSame(1, $this->repository->countActiveByUser($other));
    }

    /**
     * Verifies an active task is found by url while a finished one is not.
     */
    public function testFindActiveByUserAndUrlIgnoresFinishedTasks(): void
    {
        $task = $this->createTask(urlSuffix: 'watch');

        $this->assertSame(
            $task,
            $this->repository->findActiveByUserAndUrl($this->user, \sprintf(self::URL_FORMAT, 'watch')),
        );
        $this->assertNull(
            $this->repository->findActiveByUserAndUrl($this->other, \sprintf(self::URL_FORMAT, 'watch')),
        );

        $task->setStatus(DownloadTask::STATUS_SUCCESS);
        $this->entityManager->flush();

        $this->assertNull(
            $this->repository->findActiveByUserAndUrl($this->user, \sprintf(self::URL_FORMAT, 'watch')),
        );
    }

    /**
     * Verifies the recent task list keeps error tasks and skips finished ones.
     */
    public function testGetRecentActiveTasksKeepsErrorTasksAndSkipsFinishedOnes(): void
    {
        $queued = $this->createTask();
        $error  = $this->createTask(status: DownloadTask::STATUS_ERROR, urlSuffix: 'broken');

        $success = $this->createTask(status: DownloadTask::STATUS_SUCCESS, urlSuffix: 'done');

        $recent = $this->repository->getRecentActiveTasks();

        $this->assertContains($queued, $recent);
        $this->assertContains($error, $recent);
        $this->assertNotContains($success, $recent);
        $this->assertCount(1, $this->repository->getRecentActiveTasks(1));
    }

    /**
     * Verifies every queued task is removed at once and the number is returned.
     */
    public function testDeleteQueuedRemovesEveryQueuedTask(): void
    {
        $this->createTask(urlSuffix: 'first');
        $this->createTask(urlSuffix: 'second');
        $this->createTask(status: DownloadTask::STATUS_PROCESSING, urlSuffix: 'running');

        $deleted = $this->repository->deleteQueued();
        $queued  = (int) $this->countRows("status = 'queued'");

        $this->assertGreaterThanOrEqual(2, $deleted);
        $this->assertSame(0, $queued);
        $this->assertGreaterThanOrEqual(1, $this->countRows("status = 'processing'"));
    }

    /**
     * Verifies only processing tasks are returned by the stuck task lookup.
     */
    public function testFindProcessingTasksReturnsProcessingTasksOnly(): void
    {
        $processing = $this->createTask(status: DownloadTask::STATUS_PROCESSING);
        $this->createTask();

        $found = $this->repository->findProcessingTasks();

        $this->assertContains($processing, $found);

        foreach ($found as $task) {
            $this->assertSame(DownloadTask::STATUS_PROCESSING, $task->getStatus());
        }
    }

    /**
     * Verifies a stuck task is reset to queued and other statuses are left alone.
     */
    public function testResetTaskOnlyResetsProcessingTasks(): void
    {
        $processing = $this->createTask(status: DownloadTask::STATUS_PROCESSING);
        $queued     = $this->createTask(urlSuffix: 'other');

        $this->assertTrue($this->repository->resetTask((int) $processing->getId()));
        $this->assertFalse($this->repository->resetTask((int) $queued->getId()));
        $this->assertFalse($this->repository->resetTask(9_999_999));

        $this->entityManager->clear();

        $reset = $this->repository->findById((int) $processing->getId());
        $this->assertNotNull($reset);
        $this->assertSame(DownloadTask::STATUS_QUEUED, $reset->getStatus());
        $this->assertNull($reset->getStartedAt());
        $this->assertNull($reset->getErrorMessage());
    }

    /**
     * Verifies the status counters cover every known status.
     */
    public function testGetStatusCountsCoversEveryStatus(): void
    {
        $this->createTask();
        $this->createTask(status: DownloadTask::STATUS_PROCESSING, urlSuffix: 'running');
        $this->createTask(status: DownloadTask::STATUS_SUCCESS, urlSuffix: 'done');
        $this->createTask(status: DownloadTask::STATUS_ERROR, urlSuffix: 'broken');

        $counts = $this->repository->getStatusCounts();

        $this->assertSame([
            DownloadTask::STATUS_QUEUED,
            DownloadTask::STATUS_PROCESSING,
            DownloadTask::STATUS_SUCCESS,
            DownloadTask::STATUS_ERROR,
        ], \array_keys($counts));

        foreach (\array_keys($counts) as $status) {
            $this->assertGreaterThanOrEqual(1, $counts[$status], (string) $status);
        }
    }

    private function createTask(
        string $status = DownloadTask::STATUS_QUEUED,
        string $urlSuffix = 'task',
        ?User $user = null,
    ): DownloadTask {
        $task = new DownloadTask();

        $task->setUrl(\sprintf(self::URL_FORMAT, $urlSuffix))
            ->setQuality('best')
            ->setStatus($status)
            ->setUser($user ?? $this->user)
        ;

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    private function countRows(string $where): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            \sprintf('SELECT COUNT(*) FROM download_task WHERE %s', $where),
        );
    }
}
