<?php

declare(strict_types=1);

namespace App\Service;

/**
 * A held processing lease. Keeping the lease alive means the task has a live
 * attempt; releasing it (or the process dying) makes the task reclaimable.
 */
interface TaskProcessingLeaseInterface
{
    public function release(): void;
}
