<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DownloadTaskManager implements DownloadTaskManagerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    /**
     * Atomically claims a queued task for processing. There is no wrapping transaction
     * (messenger has no doctrine_transaction middleware on purpose), so the UPDATE
     * commits immediately: the task becomes visible as "processing" while the download
     * is still running. Returning false means the task is gone, already processing,
     * or finished — the message is a duplicate and must be skipped.
     */
    public function claimProcessing(int $taskId): bool
    {
        $affected = (int) $this->entityManager->getConnection()->executeStatement(
            'UPDATE download_task SET status = :processing, started_at = NOW(), attempts = attempts + 1
             WHERE id = :id AND status = :queued',
            [
                'id'         => $taskId,
                'processing' => DownloadTask::STATUS_PROCESSING,
                'queued'     => DownloadTask::STATUS_QUEUED,
            ],
        );

        return $affected > 0;
    }

    #[\Override]
    public function markSuccess(int $taskId): void
    {
        $this->markStatus($taskId, DownloadTask::STATUS_SUCCESS);
    }

    #[\Override]
    public function markError(int $taskId, ?string $errorMessage = null): void
    {
        $this->markStatus($taskId, DownloadTask::STATUS_ERROR, $errorMessage);
    }

    private function markStatus(int $taskId, string $status, ?string $errorMessage = null): void
    {
        /** @var DownloadTask|null $task */
        $task = $this->entityManager->find(DownloadTask::class, $taskId);

        if (!$task instanceof DownloadTask) {
            return;
        }

        $task->setStatus($status)
            ->setFinishedAt(new \DateTimeImmutable())
            ->setErrorMessage($errorMessage)
        ;

        $this->entityManager->flush();
    }
}
