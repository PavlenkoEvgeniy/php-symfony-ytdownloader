<?php

declare(strict_types=1);

namespace App\Service;

use App\Message\DownloadMessage;
use App\Repository\DownloadTaskRepositoryInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Resets stuck work: processing tasks whose attempt is dead go back to queued,
 * and in-flight transport messages (fetched by a worker that then died, so never
 * acked and never redelivered) become deliverable again. A task with a live
 * processing lease is skipped, so Reset never starts a second download (see
 * CONTEXT.md "Reset" and "Stuck Task").
 *
 * The counterpart of QueuePurgeService: purge deletes, reset resumes.
 */
final readonly class QueueResetService implements QueueResetServiceInterface
{
    /** Messenger transport queue names from config/packages/messenger.yaml. */
    private const array TRANSPORT_QUEUE_NAMES = ['download_queue'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DownloadTaskRepositoryInterface $downloadTaskRepository,
        private MessageBusInterface $messageBus,
        private TaskProcessingLockInterface $taskProcessingLock,
    ) {
    }

    #[\Override]
    /**
     * @return array{processing: int, messages: int, skipped: int}
     */
    public function reset(): array
    {
        $tasks      = $this->downloadTaskRepository->findProcessingTasks();
        $connection = $this->entityManager->getConnection();
        $processing = 0;
        $skipped    = 0;

        $messages = $connection->transactional(static fn (Connection $connection): int => (int) $connection->executeStatement(
            'UPDATE messenger_messages SET delivered_at = NULL
             WHERE delivered_at IS NOT NULL AND queue_name IN (:queueNames)',
            ['queueNames' => self::TRANSPORT_QUEUE_NAMES],
            ['queueNames' => ArrayParameterType::STRING],
        ));

        foreach ($tasks as $task) {
            $taskId = $task->getIdOrFail();
            $lease  = $this->taskProcessingLock->tryAcquire($taskId);

            if (null === $lease) {
                ++$skipped;

                continue;
            }

            $reset = false;

            try {
                $reset = $this->downloadTaskRepository->resetTask($taskId);
            } finally {
                $lease->release();
            }

            if (!$reset) {
                ++$skipped;

                continue;
            }

            ++$processing;

            $telegramUserId = $task->isTelegramOrigin() && null !== $task->getTelegramChatId()
                ? (string) $task->getTelegramChatId()
                : '';

            // A killed worker may have already acked the message, so redelivery alone
            // cannot restart the task; the handler's claim guard skips duplicates.
            $this->messageBus->dispatch(new DownloadMessage(
                $task->getUrlOrFail(),
                $task->getQualityOrFail(),
                $telegramUserId,
                $taskId,
            ));
        }

        return ['processing' => $processing, 'messages' => $messages, 'skipped' => $skipped];
    }
}
