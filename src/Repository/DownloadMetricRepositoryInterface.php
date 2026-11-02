<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DownloadMetric;

interface DownloadMetricRepositoryInterface
{
    public function getSingleton(): ?DownloadMetric;
}
