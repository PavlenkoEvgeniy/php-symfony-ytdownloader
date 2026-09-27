<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\DownloadTaskRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Purges the download queue: deletes every queued task together with all pending
 * transport messages. Task rows alone do not stop work — the worker consumes messages
 * independently of them, so the transport cleanup is part of the purge (see ADR-0002).
 */
final readonly class QueuePurgeService
{
    /** Messenger transport queue names from config/packages/messenger.yaml. */
    private const TRANSPORT_QUEUE_NAMES = ['download_queue', 'failed_queue'];

    public function __construct(
        private EntityManagerInterface $em,
        private DownloadTaskRepository $taskRepository,
    ) {
    }

    /**
     * @return array{tasks: int, messages: int}
     */
    public function purge(): array
    {
        $connection = $this->em->getConnection();
        $tasks      = 0;
        $messages   = 0;

        $connection->transactional(function () use ($connection, &$tasks, &$messages): void {
            $tasks = $this->taskRepository->deleteQueued();

            $messages = $connection->executeStatement(
                'DELETE FROM messenger_messages WHERE delivered_at IS NULL AND queue_name IN (:queueNames)',
                ['queueNames' => self::TRANSPORT_QUEUE_NAMES],
                ['queueNames' => ArrayParameterType::STRING],
            );
        });

        return ['tasks' => $tasks, 'messages' => $messages];
    }
}
