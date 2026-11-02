<?php

declare(strict_types=1);

namespace App\Service;

interface QueueStatsServiceInterface
{
    /**
     * @return array{queued: int, processing: int, success: int, error: int, totalSize: int}
     */
    public function getStats(): array;
}
