<?php

declare(strict_types=1);

namespace App\Service;

interface DownloadMetricManagerInterface
{
    public function getTotal(): float;

    public function increment(float $size): void;
}
