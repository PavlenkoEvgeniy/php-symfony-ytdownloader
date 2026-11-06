<?php

declare(strict_types=1);

namespace App\Service;

interface TaskProcessingLockInterface
{
    /**
     * Takes a non-blocking lease for the task. Returns null when another live
     * attempt already holds it.
     */
    public function tryAcquire(int $taskId): ?TaskProcessingLeaseInterface;
}
