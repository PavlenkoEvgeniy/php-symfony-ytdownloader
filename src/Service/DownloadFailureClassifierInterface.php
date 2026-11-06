<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\DownloadFailureKind;

interface DownloadFailureClassifierInterface
{
    public function classify(string $reason): DownloadFailureKind;
}
