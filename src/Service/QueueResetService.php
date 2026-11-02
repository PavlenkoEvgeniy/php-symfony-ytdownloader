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
 * Resets stuck work: processing tasks go back to queued, and in-flight transport
 * messages (fetched by a worker that then died, so never acked and never
 * redelivered) become deliverable again. The counterpart of QueuePurgeService:
 * purge deletes, reset resumes (see CONTEXT.md "Reset").
 */
final readonly class QueueResetService implements QueueResetServiceInterface
{
    /** Messenger transport queue names from config/packages/messenger.yaml. */
    private const array TRANSPORT_QUEUE_NAMES = ['download_queue'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DownloadTaskRepositoryInterface $downloadTaskRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[\Override]
    /**
     * @return array{processing: int, messages: int}
     */
    public function reset(): array
    {
        $tasks      = $this->downloadTaskRepository->findProcessingTasks();
        $connection = $this->entityManager->getConnection();
        $processing = 0;
        $messages   = 0;

        $connection->transactional(function (Connection $connection) use (&$processing, &$messages): void {
            $processing = $this->downloadTaskRepository->resetProcessing();

            $messages = $connection->executeStatement(
                'UPDATE messenger_messages SET delivered_at = NULL
                 WHERE delivered_at IS NOT NULL AND queue_name IN (:queueNames)',
                ['queueNames' => self::TRANSPORT_QUEUE_NAMES],
                ['queueNames' => ArrayParameterType::STRING],
            );
        });

        // A killed worker may have already acked the message, so redelivery alone
        // cannot restart the task; the handler's claim guard skips duplicates.
        foreach ($tasks as $task) {
            $this->messageBus->dispatch(new DownloadMessage(
                $task->getUrlOrFail(),
                $task->getQualityOrFail(),
                '',
                $task->getIdOrFail(),
            ));
        }

        return ['processing' => $processing, 'messages' => $messages];
    }
}
