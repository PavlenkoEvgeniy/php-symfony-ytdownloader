<?php

declare(strict_types=1);

namespace App\Service;

interface QueueResetServiceInterface
{
    /**
     * @return array{processing: int, messages: int, skipped: int}
     */
    public function reset(): array;
}
