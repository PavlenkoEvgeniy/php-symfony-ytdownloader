<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\AuditChannel;

interface DownloadRetryServiceInterface
{
    /**
     * Requeues an error task as a fresh request and dispatches it. Returns false
     * when the task does not exist or is not in error.
     */
    public function reDownload(int $taskId, ?User $actor = null, ?AuditChannel $channel = null): bool;
}
