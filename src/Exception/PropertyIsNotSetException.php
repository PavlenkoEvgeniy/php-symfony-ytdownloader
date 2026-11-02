<?php

declare(strict_types=1);

namespace App\Exception;

final class PropertyIsNotSetException extends \LogicException
{
    public static function forProperty(string $property): self
    {
        return new self(\sprintf('Property "%s" is not set.', $property));
    }
}
