<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\DownloadTaskRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resets stuck work: processing tasks go back to queued, and in-flight transport
 * messages (fetched by a worker that then died, so never acked and never
 * redelivered) become deliverable again. The counterpart of QueuePurgeService:
 * purge deletes, reset resumes (see CONTEXT.md "Reset").
 */
final readonly class QueueResetService
{
    /** Messenger transport queue names from config/packages/messenger.yaml. */
    private const TRANSPORT_QUEUE_NAMES = ['download_queue'];

    public function __construct(
        private EntityManagerInterface $em,
        private DownloadTaskRepository $taskRepository,
    ) {
    }

    /**
     * @return array{processing: int, messages: int}
     */
    public function reset(): array
    {
        $connection = $this->em->getConnection();
        $processing = 0;
        $messages   = 0;

        $connection->transactional(function () use ($connection, &$processing, &$messages): void {
            $processing = $this->taskRepository->resetProcessing();

            $messages = $connection->executeStatement(
                'UPDATE messenger_messages SET delivered_at = NULL
                 WHERE delivered_at IS NOT NULL AND queue_name IN (:queueNames)',
                ['queueNames' => self::TRANSPORT_QUEUE_NAMES],
                ['queueNames' => ArrayParameterType::STRING],
            );
        });

        return ['processing' => $processing, 'messages' => $messages];
    }
}
