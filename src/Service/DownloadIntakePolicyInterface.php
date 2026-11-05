<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\DownloadIntakeDisabledException;

interface DownloadIntakePolicyInterface
{
    /**
     * Whether new download tasks are accepted from the web surface (UI and API).
     */
    public function isWebIntakeEnabled(): bool;

    /**
     * Rejects a new download task when its surface may not create one.
     *
     * @throws DownloadIntakeDisabledException
     */
    public function assertAccepts(string $origin): void;
}
