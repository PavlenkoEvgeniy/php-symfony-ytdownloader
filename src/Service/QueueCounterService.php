<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final class QueueCounterService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getQueueCount(string $queueName = 'default'): int
    {
        $query = '
            SELECT COUNT(*) 
            FROM messenger_messages 
            WHERE delivered_at IS NULL 
            AND queue_name = :queueName
        ';

        return (int) $this->connection->fetchOne($query, ['queueName' => $queueName]);
    }
}
