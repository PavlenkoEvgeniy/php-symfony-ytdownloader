<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DownloadTaskManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function markProcessing(int $taskId): void
    {
        $this->markStatus($taskId, DownloadTask::STATUS_PROCESSING);
    }

    public function markSuccess(int $taskId): void
    {
        $this->markStatus($taskId, DownloadTask::STATUS_SUCCESS);
    }

    public function markError(int $taskId): void
    {
        $this->markStatus($taskId, DownloadTask::STATUS_ERROR);
    }

    private function markStatus(int $taskId, string $status): void
    {
        /** @var DownloadTask|null $task */
        $task = $this->em->find(DownloadTask::class, $taskId);

        if (null === $task) {
            return;
        }

        $task->setStatus($status);
        $this->em->flush();
    }
}
