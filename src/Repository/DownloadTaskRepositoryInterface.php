<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DownloadTask;
use App\Entity\User;

interface DownloadTaskRepositoryInterface
{
    public function findById(int $id): ?DownloadTask;

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

    public function findActiveByUserAndUrl(User $user, string $url): ?DownloadTask;

    public function deleteQueued(): int;

    /**
     * @return DownloadTask[]
     */
    public function findProcessingTasks(): array;

    public function resetProcessing(): int;

    /**
     * @return array<string, int>
     */
    public function getStatusCounts(): array;
}
