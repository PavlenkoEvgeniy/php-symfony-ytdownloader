<?php

declare(strict_types=1);

namespace App\Service;

interface DownloadTaskManagerInterface
{
    public function claimProcessing(int $taskId): bool;

    public function markSuccess(int $taskId): void;

    public function markError(int $taskId, ?string $errorMessage = null): void;
}
