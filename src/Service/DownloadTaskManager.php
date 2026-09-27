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

    /**
     * Atomically claims a queued task for processing. There is no wrapping transaction
     * (messenger has no doctrine_transaction middleware on purpose), so the UPDATE
     * commits immediately: the task becomes visible as "processing" while the download
     * is still running. Returning false means the task is gone, already processing,
     * or finished — the message is a duplicate and must be skipped.
     */
    public function claimProcessing(int $taskId): bool
    {
        $affected = (int) $this->em->getConnection()->executeStatement(
            'UPDATE download_task SET status = :processing WHERE id = :id AND status = :queued',
            [
                'id'         => $taskId,
                'processing' => DownloadTask::STATUS_PROCESSING,
                'queued'     => DownloadTask::STATUS_QUEUED,
            ],
        );

        return $affected > 0;
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
