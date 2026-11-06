<?php

declare(strict_types=1);

namespace App\Service;

interface ProgressReporterInterface
{
    public function report(int $percent): void;
}
