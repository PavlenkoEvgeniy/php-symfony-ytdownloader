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
     * Atomically claims a task for processing. There is no wrapping transaction
     * (messenger has no doctrine_transaction middleware on purpose), so the UPDATE
     * commits immediately: the task becomes visible as "processing" while the download
     * is still running. A task in processing can be claimed when its previous attempt
     * is dead; the processing lease, not the status, guarantees a single live attempt.
     * Returning false means the task is gone or finished — the message is a duplicate
     * and must be skipped.
     */
    public function claimProcessing(int $taskId): bool
    {
        $affected = (int) $this->entityManager->getConnection()->executeStatement(
            'UPDATE download_task
                SET status = :processing, started_at = NOW(), attempts = attempts + 1, error_message = NULL
              WHERE id = :id AND status IN (:queued, :processing)',
            [
                'id'         => $taskId,
                'processing' => DownloadTask::STATUS_PROCESSING,
                'queued'     => DownloadTask::STATUS_QUEUED,
            ],
        );

        return $affected > 0;
    }

    #[\Override]
    /**
     * Returns a transiently failed task to the queue while keeping the attempt
     * counter and the reason visible until the next attempt starts.
     */
    public function markRetryScheduled(int $taskId, string $errorMessage): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE download_task
                SET status = :queued, error_message = :errorMessage, finished_at = NULL
              WHERE id = :id AND status = :processing',
            [
                'id'           => $taskId,
                'queued'       => DownloadTask::STATUS_QUEUED,
                'processing'   => DownloadTask::STATUS_PROCESSING,
                'errorMessage' => $errorMessage,
            ],
        );
    }

    #[\Override]
    /**
     * Requeues an error task as a fresh request: the previous error, finish time
     * and attempt counter are cleared.
     */
    public function requeueError(int $taskId): bool
    {
        $affected = (int) $this->entityManager->getConnection()->executeStatement(
            'UPDATE download_task
                SET status = :queued, error_message = NULL, finished_at = NULL, attempts = 0, started_at = NULL
              WHERE id = :id AND status = :error',
            [
                'id'     => $taskId,
                'queued' => DownloadTask::STATUS_QUEUED,
                'error'  => DownloadTask::STATUS_ERROR,
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

    #[\Override]
    public function markDeliveryStatus(int $taskId, string $status, ?int $telegramMessageId = null): void
    {
        /** @var DownloadTask|null $task */
        $task = $this->entityManager->find(DownloadTask::class, $taskId);

        if (!$task instanceof DownloadTask) {
            return;
        }

        $task->setDeliveryStatus($status);

        if (DownloadTask::DELIVERY_SENT === $status) {
            $task->setDeliveredAt(new \DateTimeImmutable());
        }

        if (null !== $telegramMessageId) {
            $task->setTelegramMessageId($telegramMessageId);
        }

        $this->entityManager->flush();
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
