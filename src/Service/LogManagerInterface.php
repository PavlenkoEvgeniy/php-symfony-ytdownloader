<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Log;

interface LogManagerInterface
{
    public function create(string $type, string $message, ?float $size = null): Log;

    public function flush(): void;
}
