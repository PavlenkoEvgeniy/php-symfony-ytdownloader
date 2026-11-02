<?php

declare(strict_types=1);

namespace App\Service;

interface QueuePurgeServiceInterface
{
    /**
     * @return array{tasks: int, messages: int}
     */
    public function purge(): array;
}
