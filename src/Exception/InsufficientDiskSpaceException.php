<?php

declare(strict_types=1);

namespace App\Exception;

final class InsufficientDiskSpaceException extends \DomainException
{
    public static function create(float $freeBytes, int $minFreeBytes): self
    {
        return new self(\sprintf(
            'Not enough free disk space: %.0f bytes available, %d bytes required.',
            $freeBytes,
            $minFreeBytes,
        ));
    }
}
