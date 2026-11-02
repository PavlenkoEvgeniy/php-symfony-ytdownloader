<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DownloadTask;

interface DownloadTaskRepositoryInterface
{
    /**
     * @return DownloadTask[]
     */
    public function getRecentActiveTasks(int $limit = 50): array;

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
