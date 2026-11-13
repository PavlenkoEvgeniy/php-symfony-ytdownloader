<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DownloadTask;
use App\Entity\User;

interface DownloadTaskRepositoryInterface
{
    public function findById(int $id): ?DownloadTask;

    /**
     * Scalar existence probe that bypasses the identity map, so a task
     * deleted by another connection is seen as gone immediately.
     */
    public function existsById(int $id): bool;

    /**
     * @return DownloadTask[]
     */
    public function getRecentActiveTasks(int $limit = 50): array;

    /**
     * @return DownloadTask[]
     */
    public function findActiveByUser(User $user, int $limit = 10): array;

    public function countActiveByUser(User $user): int;

    /**
     * @return DownloadTask[]
     */
    public function findQueuedByUser(User $user): array;

    public function deleteQueuedByUser(User $user): int;

    /**
     * Removes every active task (queued and processing) of a user and
     * returns their number; /cancel uses it to free the user's slot.
     */
    public function deleteActiveByUser(User $user): int;

    public function findActiveByUserAndUrl(User $user, string $url): ?DownloadTask;

    public function deleteQueued(): int;

    public function deleteErrorTasks(): int;

    /**
     * @return DownloadTask[]
     */
    public function findProcessingTasks(): array;

    public function resetTask(int $taskId): bool;

    /**
     * @return array<string, int>
     */
    public function getStatusCounts(): array;
}
