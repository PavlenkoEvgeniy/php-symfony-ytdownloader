<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DownloadSettings;

interface DownloadSettingsRepositoryInterface
{
    /**
     * Returns the singleton settings row, or an unsaved entity with defaults
     * when the row does not exist yet.
     */
    public function getSettings(): DownloadSettings;
}
