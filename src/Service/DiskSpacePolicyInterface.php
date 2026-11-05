<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\InsufficientDiskSpaceException;

interface DiskSpacePolicyInterface
{
    public function getFreeBytes(): float;

    public function getMinFreeBytes(): int;

    public function isLow(): bool;

    /**
     * @throws InsufficientDiskSpaceException
     */
    public function assertCanAccept(): void;
}
